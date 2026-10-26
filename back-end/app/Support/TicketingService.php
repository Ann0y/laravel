<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use App\Models\ScenicArea;
use App\Models\SysUser;
use App\Models\Visitor;
use App\Models\Operator;
use App\Models\OpenDay;
use App\Models\TicketType;
use App\Models\TicketStock;
use App\Models\SaleOrder;
use App\Models\Ticket;
use App\Models\RefundRecord;

/**
 * 业务领域助手：库存 / 订单 / 退票的核心口径（对应需求文档 E1 与 3.1–3.3 流程）
 */
class TicketingService
{
    /** 入园日期滚动窗口：保证未来 7 天均存在开放日与各票种额度行 */
    public function ensureOpenDays(): void
    {
        DB::transaction(function () {
            $area = ScenicArea::query()->orderBy('id')->first();
            if (!$area) {
                return;
            }

            $types = TicketType::where('del_flag', 0)->get();
            $defaultQuotas = [
                'ADULT' => 500, 'CHILD' => 200, 'STUDENT' => 200, 'SENIOR' => 150,
            ];

            for ($i = 0; $i < 7; $i++) {
                $date = now()->addDays($i)->toDateString();
                $day = OpenDay::firstOrCreate(
                    ['area_id' => $area->id, 'open_date' => $date],
                    ['capacity' => $area->default_capacity, 'status' => '开放'],
                );

                foreach ($types as $type) {
                    TicketStock::firstOrCreate(
                        ['open_day_id' => $day->id, 'type_id' => $type->id],
                        ['quota' => $defaultQuotas[$type->type_code] ?? 300, 'sold_count' => 0, 'status' => '正常'],
                    );
                }
            }
        });
    }

    public function remaining(int $openDayId, int $typeId): int
    {
        $stock = TicketStock::where('open_day_id', $openDayId)->where('type_id', $typeId)->first();
        if (!$stock) {
            return 0;
        }

        return max(0, $stock->quota - $stock->sold_count);
    }

    /** 提交订单（E1：事务内锁定额度行，校验后占额，防并发超卖） */
    public function createOrder(Visitor $visitor, string $typeCode, string $visitDate, int $quantity, string $channel, ?Operator $operator = null, ?string $payMethod = null): SaleOrder
    {
        return DB::transaction(function () use ($visitor, $typeCode, $visitDate, $quantity, $channel, $operator, $payMethod) {
            $type = TicketType::where('type_code', $typeCode)->where('del_flag', 0)->first();
            $this->failUnless($type && $type->status === '在售', '该票种已停售，请重新选择');

            $day = OpenDay::where('open_date', $visitDate)->where('status', '开放')->first();
            $this->failUnless($day, '该日期闭园或未开放，请另选日期');

            $stock = TicketStock::where('open_day_id', $day->id)->where('type_id', $type->id)->lockForUpdate()->first();
            $rest = $stock ? max(0, $stock->quota - $stock->sold_count) : 0;
            $this->failUnless($rest >= $quantity, "该日期“{$type->type_name}”仅剩 {$rest} 张，请减少数量或另选日期");

            // 占额
            $stock->sold_count += $quantity;
            if ($stock->sold_count >= $stock->quota) {
                $stock->status = '已售罄';
            }
            $stock->save();

            $order = SaleOrder::create([
                'order_no' => $this->makeOrderNo(),
                'visitor_id' => $visitor->id,
                'type_id' => $type->id,
                'visit_date' => $visitDate,
                'quantity' => $quantity,
                'unit_price' => $type->price,
                'total_amount' => $type->price * $quantity,
                'channel' => $channel,
                // 窗口渠道登记线下支付方式（现金 / 扫码，F06）；线上渠道出票时补记模拟支付
                'pay_method' => $channel === '窗口' ? ($payMethod ?: '线下') : null,
                'operator_id' => $operator?->id,
                'status' => '待支付',
            ]);

            // 窗口渠道：提交即视为支付成功并出票（需求文档 3.2）
            if ($channel === '窗口') {
                $this->payOrder($order);
            }

            return $order->refresh()->loadMissing('tickets');
        });
    }

    /** 模拟支付：出票（每张一张票，含唯一核销码） */
    public function payOrder(SaleOrder $order): SaleOrder
    {
        return DB::transaction(function () use ($order) {
            $order = SaleOrder::whereKey($order->id)->lockForUpdate()->first();
            $this->failUnless($order->status === '待支付', '订单状态已变化，仅待支付订单可支付');

            $order->status = '已支付';
            $order->pay_time = now();
            $order->pay_method = $order->pay_method ?: '模拟支付';
            $order->save();

            $type = $order->type;
            for ($i = 0; $i < $order->quantity; $i++) {
                Ticket::create([
                    'ticket_no' => $this->makeTicketNo(),
                    'order_id' => $order->id,
                    'type_id' => $order->type_id,
                    'visit_date' => $order->visit_date->toDateString(),
                    'unit_price' => $type->price,
                    'status' => '未检票',
                ]);
            }

            return $order->refresh()->loadMissing('tickets');
        });
    }

    /** 取消待支付订单：额度回补 */
    public function cancelOrder(SaleOrder $order): SaleOrder
    {
        return DB::transaction(function () use ($order) {
            $order = SaleOrder::whereKey($order->id)->lockForUpdate()->first();
            $this->failUnless($order->status === '待支付', '仅待支付订单可以取消');

            $order->status = '已取消';
            $order->cancel_time = now();
            $order->save();

            $stock = TicketStock::where('open_day_id', OpenDay::where('open_date', $order->visit_date->toDateString())->value('id'))
                ->where('type_id', $order->type_id)->first();
            if ($stock) {
                $stock->sold_count = max(0, $stock->sold_count - $order->quantity);
                if ($stock->status === '已售罄' && $stock->sold_count < $stock->quota) {
                    $stock->status = '正常';
                }
                $stock->save();
            }

            return $order->refresh();
        });
    }

    /** 检票核销（票务员）：当日有效、一码一次 */
    public function checkTicket(string $ticketNo, SysUser $checker): Ticket
    {
        return DB::transaction(function () use ($ticketNo, $checker) {
            $ticket = Ticket::where('ticket_no', $ticketNo)->lockForUpdate()->first();
            $this->failUnless($ticket, '核销码不存在，请核对后重试', 404);
            $this->failUnless($ticket->status === '未检票', $ticket->status === '已检票' ? '该门票已检票，请勿重复核销' : "该门票状态为「{$ticket->status}」，不可核销");
            $this->failUnless($ticket->visit_date->isToday(), '该门票非当日有效，不能核销');
            $this->failUnless(!optional($ticket->latestRefund)->audit_status || optional($ticket->latestRefund)->audit_status !== '待审核', '该门票存在待审核的退票申请，暂不能核销');

            $ticket->status = '已检票';
            $ticket->check_time = now();
            $ticket->checker_id = $checker->id;
            $ticket->save();

            $order = $ticket->order;
            if ($order->tickets()->where('status', '未检票')->count() === 0) {
                $order->status = '已使用';
                $order->save();
            }

            return $ticket;
        });
    }

    /** 游客提交退票申请（仅游园日之前、未检票门票） */
    public function requestRefund(Visitor $visitor, string $ticketNo, string $reason): RefundRecord
    {
        return DB::transaction(function () use ($visitor, $ticketNo, $reason) {
            $ticket = Ticket::where('ticket_no', $ticketNo)->lockForUpdate()->first();
            $this->failUnless($ticket, '未找到该门票', 404);
            $this->failUnless($ticket->order->visitor_id === $visitor->id, '无权操作该门票', 403);
            $this->failUnless($ticket->visit_date->greaterThan(now()->startOfDay()), '游园日当天及之后不支持退票');
            $this->failUnless($ticket->status === '未检票', '该门票当前状态不可退票');

            $order = $ticket->order;
            $this->failUnless(in_array($order->status, ['已支付', '已使用', '退款中']), '订单当前状态不支持退票');

            $record = RefundRecord::create([
                'ticket_id' => $ticket->id,
                'visitor_id' => $visitor->id,
                'reason' => $reason,
                'refund_amount' => $ticket->unit_price,
                'apply_time' => now(),
                'audit_status' => '待审核',
            ]);

            $order->status = '退款中';
            $order->save();

            return $record;
        });
    }

    /** 退票审核：通过（回补额度,全部退完置为已退款）/ 驳回 */
    public function auditRefund(RefundRecord $record, bool $approve, ?string $opinion, SysUser $auditor): RefundRecord
    {
        return DB::transaction(function () use ($record, $approve, $opinion, $auditor) {
            $record = RefundRecord::whereKey($record->id)->lockForUpdate()->first();
            $this->failUnless($record->audit_status === '待审核', '该退款申请已处理');

            $record->audit_status = $approve ? '已通过' : '已驳回';
            $record->audit_opinion = $opinion;
            $record->audit_time = now();
            $record->auditor_id = $auditor->id;
            $record->save();

            $ticket = Ticket::whereKey($record->ticket_id)->lockForUpdate()->first();
            $order = SaleOrder::whereKey($ticket->order_id)->lockForUpdate()->first();

            if ($approve) {
                $ticket->status = '已退款';
                $ticket->save();

                // 定位额度行：不与 open_day join（两表 id/status 等列名冲突会覆盖主键），
                // 与 cancelOrder 口径一致：先查开放日 id 再锁额度行
                $openDayId = OpenDay::where('open_date', $order->visit_date->toDateString())->value('id');
                $stock = TicketStock::where('open_day_id', $openDayId)
                    ->where('type_id', $order->type_id)
                    ->lockForUpdate()->first();
                if ($stock) {
                    $stock->sold_count = max(0, $stock->sold_count - 1);
                    if ($stock->status === '已售罄' && $stock->sold_count < $stock->quota) {
                        $stock->status = '正常';
                    }
                    $stock->save();
                }

                $order->status = $order->tickets()->where('status', '<>', '已退款')->count() === 0 ? '已退款' : '已支付';
                $order->refund_time = $order->status === '已退款' ? now() : null;
            } else {
                $order->status = '已支付';
            }
            $order->save();

            return $record;
        });
    }

    private function failUnless(mixed $cond, string $message, int $code = 422): void
    {
        if (empty($cond)) {
            throw new ApiException($message, $code);
        }
    }

    private function makeOrderNo(): string
    {
        return 'SO' . now()->format('Ymd') . str_pad((string) (SaleOrder::whereDate('create_time', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function makeTicketNo(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $no = 'TK';
            for ($i = 0; $i < 12; $i++) {
                $no .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Ticket::where('ticket_no', $no)->exists());

        return $no;
    }
}

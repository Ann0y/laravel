<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use App\Models\OpenDay;
use App\Models\ParkItem;
use App\Models\ParkItemOrder;
use App\Models\ParkItemTicket;
use App\Models\SaleOrder;
use App\Models\Ticket;
use App\Models\SysUser;
use App\Models\Visitor;

/**
 * 园内项目（F17）：项目票购买与核销
 * 口径：不限量（无额度校验）、需持当日有效门票、提交即支付出票、项目票售出不可退
 */
class ParkItemService
{
    protected function failUnless(mixed $cond, string $message, int $code = 422): void
    {
        if (empty($cond)) {
            throw new ApiException($message, $code);
        }
    }

    /** 游客购买项目票（提交即支付出票） */
    public function createItemOrder(Visitor $visitor, string $itemCode, string $visitDate, int $quantity): ParkItemOrder
    {
        return DB::transaction(function () use ($visitor, $itemCode, $visitDate, $quantity) {
            $item = ParkItem::where('item_code', $itemCode)->where('del_flag', 0)->first();
            $this->failUnless($item && $item->status === '在售', '该项目已停售，请重新选择');

            $day = OpenDay::where('open_date', $visitDate)->where('status', '开放')->first();
            $this->failUnless($day, '该日期闭园或未开放，请另选日期');
            $this->failUnless($day->open_date->greaterThan(now()->startOfDay()), '项目票仅可购买未开始的游园日期');

            // 需持当日门票：游客在游玩日有未退款门票（未检票 / 已检票均可）
            $hasAdmission = Ticket::whereHas('order', fn ($q) => $q->where('visitor_id', $visitor->id))
                ->where('visit_date', $visitDate)
                ->whereIn('status', ['未检票', '已检票'])
                ->exists();
            $this->failUnless(
                $hasAdmission,
                '购买园内项目需先持该项目游玩日期的有效入园门票（未退款）',
            );

            $maxQty = 5; // 项目票单人单项目每日限购 5 份
            $this->failUnless($quantity >= 1 && $quantity <= $maxQty, "项目票单笔限购 {$maxQty} 份");

            $order = ParkItemOrder::create([
                'order_no' => $this->makeOrderNo(),
                'visitor_id' => $visitor->id,
                'item_id' => $item->id,
                'visit_date' => $visitDate,
                'quantity' => $quantity,
                'unit_price' => $item->price,
                'total_amount' => $item->price * $quantity,
                'pay_method' => '模拟支付',
                'pay_time' => now(),
                'status' => '已支付',
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                ParkItemTicket::create([
                    'ticket_code' => $this->makeTicketCode(),
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'visit_date' => $visitDate,
                    'status' => '未核销',
                ]);
            }

            return $order->refresh()->loadMissing(['tickets', 'item']);
        });
    }

    /** 项目口核销（票务员）：当日有效、一码一次 */
    public function checkItemTicket(string $ticketCode, SysUser $checker): ParkItemTicket
    {
        return DB::transaction(function () use ($ticketCode, $checker) {
            $ticket = ParkItemTicket::where('ticket_code', $ticketCode)->lockForUpdate()->first();
            $this->failUnless($ticket, '核销码不存在（入园门票与园内项目票均未匹配），请核对后重试', 404);
            $this->failUnless($ticket->status === '未核销', $ticket->status === '已核销' ? '该项目票已核销，请勿重复使用' : "该项目票状态为「{$ticket->status}」，不可核销");
            $this->failUnless($ticket->visit_date->isToday(), '该项目票非当日有效，不能核销');

            $ticket->status = '已核销';
            $ticket->check_time = now();
            $ticket->checker_id = $checker->id;
            $ticket->save();

            ParkItemTicket::syncOrderStatus($ticket->order);

            return $ticket;
        });
    }

    private function makeOrderNo(): string
    {
        return 'IO' . now()->format('Ymd') . str_pad((string) (ParkItemOrder::whereDate('create_time', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function makeTicketCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $no = 'PI';
            for ($i = 0; $i < 12; $i++) {
                $no .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (ParkItemTicket::where('ticket_code', $no)->exists());

        return $no;
    }
}

<?php

namespace App\Support;

use App\Models\ScenicArea;
use App\Models\TicketType;
use App\Models\RefundRecord;
use App\Models\Ticket;
use App\Models\TicketReview;
use App\Models\SaleOrder;
use App\Models\TicketStock;
use App\Models\ParkItem;
use App\Models\ParkItemOrder;

/**
 * 出参格式化：数据库 snake_case → 前端 camelCase，口径与前端类型一致
 */
class Formatter
{
    public static function area(ScenicArea $area): array
    {
        return [
            'name' => $area->name,
            'openTime' => $area->open_time,
            'address' => $area->address,
            'notice' => $area->notice,
            'description' => $area->description,
        ];
    }

    public static function ticketType(TicketType $t): array
    {
        return [
            'id' => $t->type_code,
            'typeCode' => $t->type_code,
            'name' => $t->type_name,
            'price' => (float) $t->price,
            'applicable' => $t->applicable,
            'refundable' => (bool) $t->refundable,
            'status' => $t->status,
        ];
    }

    public static function refund(?RefundRecord $r): ?array
    {
        return $r ? [
            'status' => $r->audit_status,
            'reason' => $r->reason,
            'amount' => (float) $r->refund_amount,
            'applyTime' => optional($r->apply_time)->toISOString(),
            'auditOpinion' => $r->audit_opinion,
        ] : null;
    }

    public static function review(?TicketReview $r, bool $maskVisitor = false): ?array
    {
        return $r ? [
            'id' => $r->id,
            'rating' => (int) $r->rating,
            'content' => $r->content,
            'createTime' => optional($r->create_time)->toISOString(),
            'visitorName' => $maskVisitor ? self::maskName($r->visitor?->user?->name) : $r->visitor?->user?->name,
        ] : null;
    }

    /** 姓名脱敏：张三 → 张*，李四（窗口票务员）→ 李****** */
    public static function maskName(?string $name): string
    {
        if (!$name) {
            return '匿名';
        }
        $first = mb_substr($name, 0, 1);

        return $first . str_repeat('*', max(1, mb_strlen($name) - 1));
    }

    public static function ticket(Ticket $t): array
    {
        return [
            'ticketNo' => $t->ticket_no,
            'typeId' => $t->type->type_code,
            'typeIdRaw' => $t->type_id,
            'visitDate' => $t->visit_date->toDateString(),
            'unitPrice' => (float) $t->unit_price,
            'status' => $t->status,
            'checkTime' => optional($t->check_time)->toISOString(),
            'refund' => self::refund($t->relationLoaded('latestRefund') ? $t->latestRefund : null),
            'review' => self::review($t->relationLoaded('review') ? $t->review : null),
        ];
    }

    public static function order(SaleOrder $o, bool $withTickets = true): array
    {
        $data = [
            'id' => $o->id,
            'orderNo' => $o->order_no,
            'visitDate' => $o->visit_date->toDateString(),
            'typeId' => $o->type->type_code,
            'typeName' => $o->type->type_name,
            'unitPrice' => (float) $o->unit_price,
            'quantity' => $o->quantity,
            'totalAmount' => (float) $o->total_amount,
            'channel' => $o->channel,
            'status' => $o->status,
            'payMethod' => $o->pay_method,
            'createTime' => optional($o->create_time)->toISOString(),
            'visitorName' => $o->visitor?->user?->name,
        ];

        if ($withTickets) {
            $o->loadMissing(['tickets.latestRefund', 'tickets.review']);
            $data['tickets'] = array_map([self::class, 'ticket'], $o->tickets->all());
        }

        return $data;
    }

    public static function stockRow(TicketStock $s): array
    {
        return [
            'id' => $s->id,
            'openDayId' => $s->open_day_id,
            'date' => $s->openDay->open_date->toDateString(),
            'typeId' => $s->type->type_code,
            'typeName' => $s->type->type_name,
            'quota' => $s->quota,
            'soldCount' => $s->sold_count,
            'remaining' => max(0, $s->quota - $s->sold_count),
            'status' => $s->status,
        ];
    }

    /** 园内项目订单（F17）：项目 + 项目票明细（核销码） */
    public static function itemOrder(ParkItemOrder $o): array
    {
        return [
            'id' => $o->id,
            'orderNo' => $o->order_no,
            'itemCode' => $o->item->item_code,
            'itemName' => $o->item->item_name,
            'description' => $o->item->description,
            'visitDate' => $o->visit_date->toDateString(),
            'quantity' => $o->quantity,
            'unitPrice' => (float) $o->unit_price,
            'totalAmount' => (float) $o->total_amount,
            'status' => $o->status,
            'payTime' => optional($o->pay_time)->toISOString(),
            'createTime' => optional($o->create_time)->toISOString(),
            'tickets' => $o->relationLoaded('tickets')
                ? $o->tickets->map(fn ($t) => [
                    'ticketCode' => $t->ticket_code,
                    'status' => $t->status,
                    'checkTime' => optional($t->check_time)->toISOString(),
                ])->values()->all()
                : [],
        ];
    }

    /** 园内项目主数据（管理端） */
    public static function parkItem(ParkItem $i): array
    {
        return [
            'id' => $i->id,
            'itemCode' => $i->item_code,
            'name' => $i->item_name,
            'price' => (float) $i->price,
            'description' => $i->description,
            'status' => $i->status,
        ];
    }
}

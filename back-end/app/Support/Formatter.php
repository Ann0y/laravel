<?php

namespace App\Support;

use App\Models\ScenicArea;
use App\Models\TicketType;
use App\Models\RefundRecord;
use App\Models\Ticket;
use App\Models\SaleOrder;
use App\Models\TicketStock;

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
            $o->loadMissing('tickets.latestRefund');
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
}

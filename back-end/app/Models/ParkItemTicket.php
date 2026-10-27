<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkItemTicket extends Model
{
    protected $table = 'park_item_ticket';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = [
        'ticket_code', 'order_id', 'item_id', 'visit_date', 'status', 'check_time', 'checker_id',
    ];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'check_time' => 'datetime', 'create_time' => 'datetime'];
    }

    public function order() { return $this->belongsTo(ParkItemOrder::class, 'order_id'); }
    public function item() { return $this->belongsTo(ParkItem::class, 'item_id'); }
    public function checker() { return $this->belongsTo(SysUser::class, 'checker_id'); }

    /** 全部核销完成时项目订单置「已使用」 */
    public static function syncOrderStatus(ParkItemOrder $order): void
    {
        $order->status = $order->tickets()->where('status', '未核销')->count() === 0 ? '已使用' : '已支付';
        $order->save();
    }
}

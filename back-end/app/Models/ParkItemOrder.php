<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkItemOrder extends Model
{
    protected $table = 'park_item_order';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = [
        'order_no', 'visitor_id', 'item_id', 'visit_date', 'quantity',
        'unit_price', 'total_amount', 'pay_method', 'pay_time', 'status',
    ];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'pay_time' => 'datetime', 'create_time' => 'datetime'];
    }

    public function item() { return $this->belongsTo(ParkItem::class, 'item_id'); }
    public function visitor() { return $this->belongsTo(Visitor::class); }
    public function tickets() { return $this->hasMany(ParkItemTicket::class, 'order_id'); }
}

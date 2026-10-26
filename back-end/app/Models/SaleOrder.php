<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleOrder extends Model
{
    protected $table = 'sale_order';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = [
        'order_no', 'visitor_id', 'type_id', 'visit_date', 'quantity', 'unit_price',
        'total_amount', 'channel', 'pay_method', 'pay_time', 'operator_id', 'status',
        'cancel_time', 'refund_time',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'float', 'total_amount' => 'float', 'visit_date' => 'date:Y-m-d',
            'create_time' => 'datetime', 'pay_time' => 'datetime', 'cancel_time' => 'datetime', 'refund_time' => 'datetime'];
    }

    public function visitor() { return $this->belongsTo(Visitor::class); }
    public function type() { return $this->belongsTo(TicketType::class, 'type_id'); }
    public function tickets() { return $this->hasMany(Ticket::class, 'order_id'); }
    public function operator() { return $this->belongsTo(Operator::class); }
}

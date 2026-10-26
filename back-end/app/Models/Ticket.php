<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'ticket';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = [
        'ticket_no', 'order_id', 'type_id', 'visit_date', 'unit_price', 'status',
        'check_time', 'checker_id',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'float', 'visit_date' => 'date:Y-m-d'];
    }

    public function order() { return $this->belongsTo(SaleOrder::class, 'order_id'); }
    public function type() { return $this->belongsTo(TicketType::class, 'type_id'); }
    public function refunds() { return $this->hasMany(RefundRecord::class, 'ticket_id'); }

    public function latestRefund()
    {
        return $this->hasOne(RefundRecord::class, 'ticket_id')->latestOfMany('id');
    }
}

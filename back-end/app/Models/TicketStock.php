<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketStock extends Model
{
    protected $table = 'ticket_stock';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['open_day_id', 'type_id', 'quota', 'sold_count', 'status'];

    public function openDay() { return $this->belongsTo(OpenDay::class, 'open_day_id'); }
    public function type() { return $this->belongsTo(TicketType::class, 'type_id'); }
}

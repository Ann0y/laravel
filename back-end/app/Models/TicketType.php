<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    protected $table = 'ticket_type';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['type_code', 'type_name', 'price', 'applicable', 'refundable', 'status'];

    protected function casts(): array
    {
        return ['price' => 'float', 'refundable' => 'boolean'];
    }

    public function stocks() { return $this->hasMany(TicketStock::class, 'type_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkItem extends Model
{
    protected $table = 'park_item';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['item_code', 'item_name', 'price', 'description', 'status', 'del_flag'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'create_time' => 'datetime'];
    }

    public function orders() { return $this->hasMany(ParkItemOrder::class, 'item_id'); }
}

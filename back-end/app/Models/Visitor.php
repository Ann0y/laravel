<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $table = 'visitor';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['user_id', 'id_card', 'phone', 'status'];

    public function user() { return $this->belongsTo(SysUser::class); }
    public function orders() { return $this->hasMany(SaleOrder::class); }
}

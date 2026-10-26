<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{
    protected $table = 'operator';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['user_id', 'code', 'window_no', 'phone', 'status'];

    public function user() { return $this->belongsTo(SysUser::class); }
}

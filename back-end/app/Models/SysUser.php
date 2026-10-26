<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class SysUser extends Authenticatable
{
    protected $table = 'sys_user';
    public $timestamps = false;

    const CREATED_AT = 'create_time';

    protected $fillable = ['username', 'password', 'name', 'role', 'status'];
    protected $hidden = ['password'];

    public function visitor() { return $this->hasOne(Visitor::class); }
    public function operator() { return $this->hasOne(Operator::class); }
}

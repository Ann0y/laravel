<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScenicArea extends Model
{
    protected $table = 'scenic_area';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['name', 'description', 'address', 'open_time', 'notice', 'default_capacity'];

    public function openDays() { return $this->hasMany(OpenDay::class, 'area_id'); }
}

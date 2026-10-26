<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenDay extends Model
{
    protected $table = 'open_day';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['area_id', 'open_date', 'capacity', 'status'];

    protected function casts(): array
    {
        return ['open_date' => 'date:Y-m-d'];
    }

    public function area() { return $this->belongsTo(ScenicArea::class, 'area_id'); }
    public function stocks() { return $this->hasMany(TicketStock::class, 'open_day_id'); }
}

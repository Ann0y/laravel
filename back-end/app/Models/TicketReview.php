<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketReview extends Model
{
    protected $table = 'ticket_review';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = ['ticket_id', 'visitor_id', 'rating', 'content'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'create_time' => 'datetime',
        ];
    }

    public function ticket() { return $this->belongsTo(Ticket::class); }
    public function visitor() { return $this->belongsTo(Visitor::class); }
}

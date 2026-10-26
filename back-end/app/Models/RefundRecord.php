<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRecord extends Model
{
    protected $table = 'refund_record';
    public $timestamps = false;
    const CREATED_AT = 'create_time';

    protected $fillable = [
        'ticket_id', 'visitor_id', 'reason', 'refund_amount', 'apply_time',
        'audit_status', 'auditor_id', 'audit_opinion', 'audit_time',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'float',
            'apply_time' => 'datetime',
            'audit_time' => 'datetime',
            'create_time' => 'datetime',
        ];
    }

    public function ticket() { return $this->belongsTo(Ticket::class, 'ticket_id'); }
    public function visitor() { return $this->belongsTo(Visitor::class); }
    public function auditor() { return $this->belongsTo(SysUser::class, 'auditor_id'); }
}

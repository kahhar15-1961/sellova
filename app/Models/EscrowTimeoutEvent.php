<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscrowTimeoutEvent extends Model
{
    protected $table = 'escrow_timeout_events';

    protected $fillable = [
        'uuid',
        'order_id',
        'escrow_account_id',
        'event_type',
        'status',
        'action_taken',
        'metadata_json',
        'scheduled_for',
        'processed_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'scheduled_for' => 'datetime',
        'processed_at' => 'datetime',
    ];
}


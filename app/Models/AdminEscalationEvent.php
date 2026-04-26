<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminEscalationEvent extends Model
{
    public $timestamps = false;

    protected $table = 'admin_escalation_events';

    protected $fillable = [
        'incident_id',
        'actor_user_id',
        'event_type',
        'payload_json',
        'created_at',
    ];

    protected $casts = [
        'incident_id' => 'integer',
        'actor_user_id' => 'integer',
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(AdminEscalationIncident::class, 'incident_id');
    }

    public function actor_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminEscalationIncident extends Model
{
    protected $table = 'admin_escalation_incidents';

    protected $fillable = [
        'uuid',
        'queue_code',
        'target_type',
        'target_id',
        'status',
        'severity',
        'current_ladder_level',
        'reason_code',
        'assigned_user_id',
        'sla_breached_at',
        'opened_at',
        'ack_due_at',
        'resolve_due_at',
        'next_ladder_at',
        'last_ladder_triggered_at',
        'acknowledged_at',
        'resolved_at',
        'last_notified_at',
        'meta_json',
    ];

    protected $casts = [
        'target_id' => 'integer',
        'assigned_user_id' => 'integer',
        'current_ladder_level' => 'integer',
        'sla_breached_at' => 'datetime',
        'opened_at' => 'datetime',
        'ack_due_at' => 'datetime',
        'resolve_due_at' => 'datetime',
        'next_ladder_at' => 'datetime',
        'last_ladder_triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'meta_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function assigned_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdminEscalationEvent::class, 'incident_id');
    }

    public function runbookExecutions(): HasMany
    {
        return $this->hasMany(AdminRunbookExecution::class, 'incident_id');
    }

    public function commsDeliveryLogs(): HasMany
    {
        return $this->hasMany(AdminCommsDeliveryLog::class, 'incident_id');
    }
}

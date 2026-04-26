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
        'reason_code',
        'assigned_user_id',
        'sla_breached_at',
        'opened_at',
        'acknowledged_at',
        'resolved_at',
        'last_notified_at',
        'meta_json',
    ];

    protected $casts = [
        'target_id' => 'integer',
        'assigned_user_id' => 'integer',
        'sla_breached_at' => 'datetime',
        'opened_at' => 'datetime',
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
}

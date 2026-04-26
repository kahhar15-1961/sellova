<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminEscalationPolicy extends Model
{
    protected $table = 'admin_escalation_policies';

    protected $fillable = [
        'queue_code',
        'default_severity',
        'auto_assign_on_call',
        'on_call_role_code',
        'ack_sla_minutes',
        'resolve_sla_minutes',
        'comms_integration_id',
        'escalation_ladder_json',
        'is_enabled',
    ];

    protected $casts = [
        'auto_assign_on_call' => 'boolean',
        'ack_sla_minutes' => 'integer',
        'resolve_sla_minutes' => 'integer',
        'comms_integration_id' => 'integer',
        'escalation_ladder_json' => 'array',
        'is_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function comms_integration(): BelongsTo
    {
        return $this->belongsTo(AdminCommsIntegration::class, 'comms_integration_id');
    }
}

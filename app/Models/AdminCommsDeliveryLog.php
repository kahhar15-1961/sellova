<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminCommsDeliveryLog extends Model
{
    protected $table = 'admin_comms_delivery_logs';

    protected $fillable = [
        'incident_id',
        'integration_id',
        'event_type',
        'status',
        'attempt_count',
        'last_error',
        'next_retry_at',
        'delivered_at',
        'request_payload_json',
    ];

    protected $casts = [
        'incident_id' => 'integer',
        'integration_id' => 'integer',
        'attempt_count' => 'integer',
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
        'request_payload_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(AdminEscalationIncident::class, 'incident_id');
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(AdminCommsIntegration::class, 'integration_id');
    }
}

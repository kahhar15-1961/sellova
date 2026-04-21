<?php

namespace App\Models;

use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $provider
 * @property string|null $provider_event_id
 * @property string|null $event_type
 * @property array $payload_json
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property string $processing_status
 */
class PaymentWebhookEvent extends Model
{
    use TransactionSensitive;

    protected $table = 'payment_webhook_events';

    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'payload_json',
        'received_at',
        'processed_at',
        'processing_status',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'processing_status' => 'string',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    // No direct Eloquent relationships.
}

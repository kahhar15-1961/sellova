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
 * @property string|null $uuid
 * @property string|null $aggregate_type
 * @property int $aggregate_id
 * @property string|null $event_type
 * @property array $payload_json
 * @property string $status
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $available_at
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OutboxEvent extends Model
{
    use TransactionSensitive;

    protected $table = 'outbox_events';

    protected $fillable = [
        'uuid',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload_json',
        'status',
        'attempts',
        'available_at',
        'published_at',
    ];

    protected $casts = [
        'aggregate_id' => 'integer',
        'payload_json' => 'array',
        'status' => 'string',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    // No direct Eloquent relationships.
}

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
 * @property int $escrow_account_id
 * @property string $event_type
 * @property string $amount
 * @property string|null $currency
 * @property string|null $from_state
 * @property string|null $to_state
 * @property int $actor_user_id
 * @property string|null $reference_type
 * @property int $reference_id
 * @property int $idempotency_key_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\EscrowAccount|null $escrow_account
 * @property-read \App\Models\User|null $actor_user
 * @property-read \App\Models\IdempotencyKey|null $idempotency_key
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisputeDecision> $disputeDecisions
 */
class EscrowEvent extends Model
{
    use TransactionSensitive;

    protected $table = 'escrow_events';

    protected $fillable = [
        'uuid',
        'escrow_account_id',
        'event_type',
        'amount',
        'currency',
        'from_state',
        'to_state',
        'actor_user_id',
        'reference_type',
        'reference_id',
        'idempotency_key_id',
    ];

    protected $casts = [
        'escrow_account_id' => 'integer',
        'event_type' => 'string',
        'amount' => 'decimal:4',
        'actor_user_id' => 'integer',
        'reference_id' => 'integer',
        'idempotency_key_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function escrow_account(): BelongsTo
    {
        return $this->belongsTo(EscrowAccount::class, 'escrow_account_id');
    }

    public function actor_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function idempotency_key(): BelongsTo
    {
        return $this->belongsTo(IdempotencyKey::class, 'idempotency_key_id');
    }

    public function disputeDecisions(): HasMany
    {
        return $this->hasMany(DisputeDecision::class, 'escrow_event_id');
    }
}

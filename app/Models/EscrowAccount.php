<?php

namespace App\Models;

use App\Domain\Enums\EscrowState;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $order_id
 * @property EscrowState $state
 * @property string|null $currency
 * @property string $held_amount
 * @property string $released_amount
 * @property string $refunded_amount
 * @property Carbon|null $held_at
 * @property Carbon|null $closed_at
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 * @property-read Collection<int, EscrowEvent> $escrowEvents
 */
class EscrowAccount extends Model
{
    use TransactionSensitive;

    protected $table = 'escrow_accounts';

    protected $fillable = [
        'uuid',
        'order_id',
        'state',
        'currency',
        'held_amount',
        'escrow_fee',
        'released_amount',
        'refunded_amount',
        'held_at',
        'started_at',
        'expires_at',
        'released_at',
        'auto_release_at',
        'release_method',
        'dispute_deadline_at',
        'delivery_deadline_at',
        'closed_at',
        'version',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'state' => EscrowState::class,
        'held_amount' => 'decimal:4',
        'escrow_fee' => 'decimal:4',
        'released_amount' => 'decimal:4',
        'refunded_amount' => 'decimal:4',
        'held_at' => 'datetime',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'auto_release_at' => 'datetime',
        'dispute_deadline_at' => 'datetime',
        'delivery_deadline_at' => 'datetime',
        'closed_at' => 'datetime',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function escrowEvents(): HasMany
    {
        return $this->hasMany(EscrowEvent::class, 'escrow_account_id');
    }
}

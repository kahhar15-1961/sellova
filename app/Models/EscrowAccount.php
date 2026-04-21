<?php

namespace App\Models;

use App\Domain\Enums\EscrowState;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $order_id
 * @property EscrowState $state
 * @property string|null $currency
 * @property string $held_amount
 * @property string $released_amount
 * @property string $refunded_amount
 * @property \Illuminate\Support\Carbon|null $held_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order|null $order
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EscrowEvent> $escrowEvents
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
        'released_amount',
        'refunded_amount',
        'held_at',
        'closed_at',
        'version',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'state' => EscrowState::class,
        'held_amount' => 'decimal:4',
        'released_amount' => 'decimal:4',
        'refunded_amount' => 'decimal:4',
        'held_at' => 'datetime',
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

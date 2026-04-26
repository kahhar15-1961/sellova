<?php

namespace App\Models;

use App\Domain\Enums\OrderStatus;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string|null $order_number
 * @property int $buyer_user_id
 * @property OrderStatus $status
 * @property string|null $currency
 * @property string $gross_amount
 * @property string $discount_amount
 * @property string $fee_amount
 * @property string $net_amount
 * @property Carbon|null $placed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $buyer
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read Collection<int, OrderStateTransition> $orderStateTransitions
 * @property-read Collection<int, PaymentIntent> $paymentIntents
 * @property-read Collection<int, PaymentTransaction> $paymentTransactions
 * @property-read EscrowAccount|null $escrowAccount
 * @property-read Collection<int, DisputeCase> $disputeCases
 */
class Order extends Model
{
    use TransactionSensitive;

    protected $table = 'orders';

    protected $fillable = [
        'uuid',
        'order_number',
        'buyer_user_id',
        'status',
        'currency',
        'gross_amount',
        'discount_amount',
        'fee_amount',
        'net_amount',
        'placed_at',
        'completed_at',
    ];

    protected $casts = [
        'buyer_user_id' => 'integer',
        'status' => OrderStatus::class,
        'gross_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'fee_amount' => 'decimal:4',
        'net_amount' => 'decimal:4',
        'placed_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function orderStateTransitions(): HasMany
    {
        return $this->hasMany(OrderStateTransition::class, 'order_id');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class, 'order_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'order_id');
    }

    public function escrowAccount(): HasOne
    {
        return $this->hasOne(EscrowAccount::class, 'order_id');
    }

    public function disputeCases(): HasMany
    {
        return $this->hasMany(DisputeCase::class, 'order_id');
    }
}

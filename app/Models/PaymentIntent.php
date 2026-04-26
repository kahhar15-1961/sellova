<?php

namespace App\Models;

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
 * @property string|null $provider
 * @property string|null $provider_intent_ref
 * @property string $status
 * @property string $amount
 * @property string|null $currency
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 * @property-read Collection<int, PaymentTransaction> $paymentTransactions
 * @property-read Collection<int, MembershipSubscription> $membershipSubscriptions
 */
class PaymentIntent extends Model
{
    use TransactionSensitive;

    protected $table = 'payment_intents';

    protected $fillable = [
        'uuid',
        'order_id',
        'provider',
        'provider_intent_ref',
        'status',
        'amount',
        'currency',
        'expires_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'status' => 'string',
        'amount' => 'decimal:4',
        'expires_at' => 'datetime',
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

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_intent_id');
    }

    public function membershipSubscriptions(): HasMany
    {
        return $this->hasMany(MembershipSubscription::class, 'payment_intent_id');
    }
}

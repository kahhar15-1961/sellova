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
 * @property int $payment_intent_id
 * @property int $order_id
 * @property string|null $provider_txn_ref
 * @property string $txn_type
 * @property string $status
 * @property string $amount
 * @property array $raw_payload_json
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\PaymentIntent|null $payment_intent
 * @property-read \App\Models\Order|null $order
 */
class PaymentTransaction extends Model
{
    use TransactionSensitive;

    protected $table = 'payment_transactions';

    protected $fillable = [
        'uuid',
        'payment_intent_id',
        'order_id',
        'provider_txn_ref',
        'txn_type',
        'status',
        'amount',
        'raw_payload_json',
        'processed_at',
    ];

    protected $casts = [
        'payment_intent_id' => 'integer',
        'order_id' => 'integer',
        'txn_type' => 'string',
        'status' => 'string',
        'amount' => 'decimal:4',
        'raw_payload_json' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function payment_intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

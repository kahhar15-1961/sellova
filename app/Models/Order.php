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
 * @property int|null $seller_user_id
 * @property int|null $primary_product_id
 * @property string|null $product_type
 * @property OrderStatus $status
 * @property string|null $fulfillment_state
 * @property string|null $currency
 * @property string $gross_amount
 * @property string $discount_amount
 * @property string $fee_amount
 * @property string $net_amount
 * @property string|null $promo_code
 * @property string|null $shipping_method
 * @property string|null $shipping_address_id
 * @property string|null $shipping_recipient_name
 * @property string|null $shipping_address_line
 * @property string|null $shipping_phone
 * @property string|null $courier_company
 * @property string|null $tracking_id
 * @property string|null $tracking_url
 * @property string|null $shipping_note
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $seller_deadline_at
 * @property Carbon|null $seller_reminder_at
 * @property Carbon|null $placed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
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
        'seller_user_id',
        'primary_product_id',
        'product_type',
        'status',
        'fulfillment_state',
        'currency',
        'gross_amount',
        'discount_amount',
        'fee_amount',
        'net_amount',
        'promo_code',
        'shipping_method',
        'shipping_address_id',
        'shipping_recipient_name',
        'shipping_address_line',
        'shipping_phone',
        'courier_company',
        'tracking_id',
        'tracking_url',
        'shipping_note',
        'shipped_at',
        'delivered_at',
        'delivery_submitted_at',
        'buyer_review_started_at',
        'escrow_status',
        'escrow_amount',
        'escrow_fee',
        'escrow_started_at',
        'escrow_expires_at',
        'escrow_released_at',
        'escrow_auto_release_at',
        'escrow_release_method',
        'dispute_deadline_at',
        'delivery_deadline_at',
        'delivery_status',
        'delivery_note',
        'delivery_version',
        'delivery_files_count',
        'buyer_confirmed_at',
        'seller_deadline_at',
        'seller_reminder_at',
        'buyer_review_expires_at',
        'reminder_1_at',
        'reminder_2_at',
        'escalation_at',
        'escalation_warning_at',
        'auto_release_at',
        'release_eligible_at',
        'expires_at',
        'unpaid_reminder_at',
        'timeout_policy_snapshot_json',
        'placed_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'buyer_user_id' => 'integer',
        'seller_user_id' => 'integer',
        'primary_product_id' => 'integer',
        'status' => OrderStatus::class,
        'gross_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'fee_amount' => 'decimal:4',
        'escrow_amount' => 'decimal:4',
        'escrow_fee' => 'decimal:4',
        'net_amount' => 'decimal:4',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_submitted_at' => 'datetime',
        'buyer_review_started_at' => 'datetime',
        'escrow_started_at' => 'datetime',
        'escrow_expires_at' => 'datetime',
        'escrow_released_at' => 'datetime',
        'escrow_auto_release_at' => 'datetime',
        'dispute_deadline_at' => 'datetime',
        'delivery_deadline_at' => 'datetime',
        'buyer_confirmed_at' => 'datetime',
        'seller_deadline_at' => 'datetime',
        'seller_reminder_at' => 'datetime',
        'buyer_review_expires_at' => 'datetime',
        'reminder_1_at' => 'datetime',
        'reminder_2_at' => 'datetime',
        'escalation_at' => 'datetime',
        'escalation_warning_at' => 'datetime',
        'auto_release_at' => 'datetime',
        'release_eligible_at' => 'datetime',
        'expires_at' => 'datetime',
        'unpaid_reminder_at' => 'datetime',
        'timeout_policy_snapshot_json' => 'array',
        'placed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function primaryProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'primary_product_id');
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

    public function digitalDeliveries(): HasMany
    {
        return $this->hasMany(DigitalDelivery::class, 'order_id');
    }

    public function latestDigitalDelivery(): HasOne
    {
        return $this->hasOne(DigitalDelivery::class, 'order_id')->latestOfMany('id');
    }

    public function escrowTimeoutEvents(): HasMany
    {
        return $this->hasMany(EscrowTimeoutEvent::class, 'order_id');
    }
}

<?php

namespace App\Models;

use App\Domain\Enums\MembershipSubscriptionStatus;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $seller_profile_id
 * @property int $membership_plan_id
 * @property MembershipSubscriptionStatus $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property string $renewal_mode
 * @property int $payment_intent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SellerProfile|null $seller_profile
 * @property-read \App\Models\MembershipPlan|null $membership_plan
 * @property-read \App\Models\PaymentIntent|null $payment_intent
 */
class MembershipSubscription extends Model
{
    use TransactionSensitive;

    protected $table = 'membership_subscriptions';

    protected $fillable = [
        'uuid',
        'seller_profile_id',
        'membership_plan_id',
        'status',
        'started_at',
        'expires_at',
        'cancelled_at',
        'suspended_at',
        'renewal_mode',
        'payment_intent_id',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'membership_plan_id' => 'integer',
        'status' => MembershipSubscriptionStatus::class,
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'suspended_at' => 'datetime',
        'renewal_mode' => 'string',
        'payment_intent_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function membership_plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function payment_intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}

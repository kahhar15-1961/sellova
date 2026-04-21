<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $user_id
 * @property string|null $display_name
 * @property string|null $legal_name
 * @property string|null $country_code
 * @property string|null $default_currency
 * @property string $verification_status
 * @property string $store_status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\KycVerification> $kycVerifications
 * @property-read \App\Models\Storefront|null $storefront
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CartItem> $cartItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WithdrawalRequest> $withdrawalRequests
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PayoutAccount> $payoutAccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MembershipSubscription> $membershipSubscriptions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 */
class SellerProfile extends Model
{
    use SoftDeletes;

    protected $table = 'seller_profiles';

    protected $fillable = [
        'uuid',
        'user_id',
        'display_name',
        'legal_name',
        'country_code',
        'default_currency',
        'verification_status',
        'store_status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'verification_status' => 'string',
        'store_status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kycVerifications(): HasMany
    {
        return $this->hasMany(KycVerification::class, 'seller_profile_id');
    }

    public function storefront(): HasOne
    {
        return $this->hasOne(Storefront::class, 'seller_profile_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_profile_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'seller_profile_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'seller_profile_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'seller_profile_id');
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(PayoutAccount::class, 'seller_profile_id');
    }

    public function membershipSubscriptions(): HasMany
    {
        return $this->hasMany(MembershipSubscription::class, 'seller_profile_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'seller_profile_id');
    }
}

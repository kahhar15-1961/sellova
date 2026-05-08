<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 * @property string|null $store_logo_url
 * @property string|null $banner_image_url
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $region
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $inside_dhaka_label
 * @property float|null $inside_dhaka_fee
 * @property string|null $outside_dhaka_label
 * @property float|null $outside_dhaka_fee
 * @property bool|null $cash_on_delivery_enabled
 * @property string|null $processing_time_label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 * @property-read Collection<int, KycVerification> $kycVerifications
 * @property-read Storefront|null $storefront
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, CartItem> $cartItems
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read Collection<int, WithdrawalRequest> $withdrawalRequests
 * @property-read Collection<int, PayoutAccount> $payoutAccounts
 * @property-read Collection<int, SellerShippingMethod> $shippingMethods
 * @property-read Collection<int, MembershipSubscription> $membershipSubscriptions
 * @property-read Collection<int, Review> $reviews
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
        'store_logo_url',
        'banner_image_url',
        'contact_email',
        'contact_phone',
        'address_line',
        'city',
        'region',
        'postal_code',
        'country',
        'inside_dhaka_label',
        'inside_dhaka_fee',
        'outside_dhaka_label',
        'outside_dhaka_fee',
        'cash_on_delivery_enabled',
        'processing_time_label',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'verification_status' => 'string',
        'store_status' => 'string',
        'inside_dhaka_fee' => 'float',
        'outside_dhaka_fee' => 'float',
        'cash_on_delivery_enabled' => 'boolean',
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

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(SellerShippingMethod::class, 'seller_profile_id');
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

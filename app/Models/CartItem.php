<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int $product_variant_id
 * @property int $seller_profile_id
 * @property int $quantity
 * @property string $unit_price_snapshot
 * @property string|null $currency_snapshot
 * @property array $metadata_snapshot_json
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Cart|null $cart
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\ProductVariant|null $product_variant
 * @property-read \App\Models\SellerProfile|null $seller_profile
 */
class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'seller_profile_id',
        'quantity',
        'unit_price_snapshot',
        'currency_snapshot',
        'metadata_snapshot_json',
    ];

    protected $casts = [
        'cart_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'seller_profile_id' => 'integer',
        'quantity' => 'integer',
        'unit_price_snapshot' => 'decimal:4',
        'metadata_snapshot_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function product_variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }
}

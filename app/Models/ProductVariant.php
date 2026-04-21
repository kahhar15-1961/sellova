<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $product_id
 * @property string|null $sku
 * @property string|null $title
 * @property string $price_delta
 * @property array $attributes_json
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product|null $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryRecord> $inventoryRecords
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CartItem> $cartItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 */
class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'uuid',
        'product_id',
        'sku',
        'title',
        'price_delta',
        'attributes_json',
        'is_active',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'price_delta' => 'decimal:4',
        'attributes_json' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function inventoryRecords(): HasMany
    {
        return $this->hasMany(InventoryRecord::class, 'product_variant_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id');
    }
}

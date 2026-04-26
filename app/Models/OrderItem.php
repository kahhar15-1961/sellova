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
 * @property int $seller_profile_id
 * @property int $product_id
 * @property int $product_variant_id
 * @property string $product_type_snapshot
 * @property string|null $title_snapshot
 * @property string|null $sku_snapshot
 * @property int $quantity
 * @property string $unit_price_snapshot
 * @property string $line_total_snapshot
 * @property array $commission_rule_snapshot_json
 * @property string $delivery_state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 * @property-read SellerProfile|null $seller_profile
 * @property-read Product|null $product
 * @property-read ProductVariant|null $product_variant
 * @property-read Collection<int, DisputeCase> $disputeCases
 * @property-read Collection<int, Review> $reviews
 */
class OrderItem extends Model
{
    use TransactionSensitive;

    protected $table = 'order_items';

    protected $fillable = [
        'uuid',
        'order_id',
        'seller_profile_id',
        'product_id',
        'product_variant_id',
        'product_type_snapshot',
        'title_snapshot',
        'sku_snapshot',
        'quantity',
        'unit_price_snapshot',
        'line_total_snapshot',
        'commission_rule_snapshot_json',
        'delivery_state',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'seller_profile_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'product_type_snapshot' => 'string',
        'quantity' => 'integer',
        'unit_price_snapshot' => 'decimal:4',
        'line_total_snapshot' => 'decimal:4',
        'commission_rule_snapshot_json' => 'array',
        'delivery_state' => 'string',
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

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function product_variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function disputeCases(): HasMany
    {
        return $this->hasMany(DisputeCase::class, 'order_item_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'order_item_id');
    }
}

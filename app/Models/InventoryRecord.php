<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $product_variant_id
 * @property int $stock_on_hand
 * @property int $stock_reserved
 * @property int $stock_sold
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product
 * @property-read ProductVariant|null $product_variant
 */
class InventoryRecord extends Model
{
    protected $table = 'inventory_records';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'stock_on_hand',
        'stock_reserved',
        'stock_sold',
        'version',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'stock_on_hand' => 'integer',
        'stock_reserved' => 'integer',
        'stock_sold' => 'integer',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function product_variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

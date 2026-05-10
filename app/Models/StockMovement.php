<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'uuid',
        'seller_profile_id',
        'seller_warehouse_id',
        'product_id',
        'product_variant_id',
        'movement_type',
        'quantity_delta',
        'stock_after',
        'reason',
        'reference',
        'created_by_user_id',
        'metadata_json',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'seller_warehouse_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'quantity_delta' => 'integer',
        'stock_after' => 'integer',
        'created_by_user_id' => 'integer',
        'metadata_json' => 'array',
    ];

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(SellerWarehouse::class, 'seller_warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

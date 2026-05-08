<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerShippingMethod extends Model
{
    protected $table = 'seller_shipping_methods';

    protected $fillable = [
        'seller_profile_id',
        'shipping_method_id',
        'price',
        'processing_time_label',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'shipping_method_id' => 'integer',
        'price' => 'float',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}

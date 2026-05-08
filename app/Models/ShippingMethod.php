<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    protected $table = 'shipping_methods';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'suggested_fee',
        'processing_time_label',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'suggested_fee' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sellerShippingMethods(): HasMany
    {
        return $this->hasMany(SellerShippingMethod::class, 'shipping_method_id');
    }
}

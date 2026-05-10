<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerWarehouse extends Model
{
    protected $table = 'seller_warehouses';

    protected $fillable = [
        'uuid',
        'seller_profile_id',
        'name',
        'code',
        'address',
        'city',
        'contact_person',
        'phone',
        'status',
        'metadata_json',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'metadata_json' => 'array',
    ];

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'seller_warehouse_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $seller_profile_id
 * @property string|null $slug
 * @property string|null $title
 * @property string|null $description
 * @property string|null $policy_text
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SellerProfile|null $seller_profile
 * @property-read Collection<int, Product> $products
 */
class Storefront extends Model
{
    protected $table = 'storefronts';

    protected $fillable = [
        'uuid',
        'seller_profile_id',
        'slug',
        'title',
        'description',
        'policy_text',
        'is_public',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'storefront_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $seller_profile_id
 * @property int $requested_by_user_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $reason
 * @property string|null $example_product_name
 * @property string $status
 * @property int|null $resolved_category_id
 * @property int|null $reviewed_by
 * @property string|null $admin_note
 * @property Carbon|null $reviewed_at
 */
class SellerCategoryRequest extends Model
{
    protected $table = 'seller_category_requests';

    protected $fillable = [
        'uuid',
        'seller_profile_id',
        'requested_by_user_id',
        'parent_id',
        'name',
        'slug',
        'reason',
        'example_product_name',
        'status',
        'resolved_category_id',
        'reviewed_by',
        'admin_note',
        'reviewed_at',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'parent_id' => 'integer',
        'resolved_category_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function resolvedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'resolved_category_id');
    }
}

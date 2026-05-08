<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property string|null $badge
 * @property string $campaign_type
 * @property string $scope_type
 * @property array<int, int>|null $target_product_ids
 * @property array<int, int>|null $target_seller_profile_ids
 * @property array<int, int>|null $target_category_ids
 * @property array<int, string>|null $target_product_types
 * @property string $currency
 * @property string $discount_type
 * @property string $discount_value
 * @property string $min_spend
 * @property string|null $max_discount_amount
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $daily_start_time
 * @property string|null $daily_end_time
 * @property int|null $usage_limit
 * @property int $priority
 * @property string|null $marketing_channel
 * @property int|null $created_by_user_id
 * @property int $used_count
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Promotion extends Model
{
    protected $table = 'promotions';

    protected $fillable = [
        'uuid',
        'code',
        'title',
        'description',
        'badge',
        'campaign_type',
        'scope_type',
        'target_product_ids',
        'target_seller_profile_ids',
        'target_category_ids',
        'target_product_types',
        'currency',
        'discount_type',
        'discount_value',
        'min_spend',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'daily_start_time',
        'daily_end_time',
        'usage_limit',
        'priority',
        'marketing_channel',
        'created_by_user_id',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'usage_limit' => 'integer',
        'priority' => 'integer',
        'created_by_user_id' => 'integer',
        'target_product_ids' => 'array',
        'target_seller_profile_ids' => 'array',
        'target_category_ids' => 'array',
        'target_product_types' => 'array',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

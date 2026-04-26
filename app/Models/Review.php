<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $order_item_id
 * @property int $buyer_user_id
 * @property int $seller_profile_id
 * @property int $product_id
 * @property int $rating
 * @property string|null $comment
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OrderItem|null $order_item
 * @property-read User|null $buyer
 * @property-read SellerProfile|null $seller_profile
 * @property-read Product|null $product
 */
class Review extends Model
{
    protected $table = 'reviews';

    protected $fillable = [
        'uuid',
        'order_item_id',
        'buyer_user_id',
        'seller_profile_id',
        'product_id',
        'rating',
        'comment',
        'status',
    ];

    protected $casts = [
        'order_item_id' => 'integer',
        'buyer_user_id' => 'integer',
        'seller_profile_id' => 'integer',
        'product_id' => 'integer',
        'rating' => 'integer',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order_item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

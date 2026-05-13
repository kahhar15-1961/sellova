<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property int $seller_user_id
 * @property int $seller_profile_id
 * @property int $buyer_user_id
 * @property int $rating
 * @property string|null $comment
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BuyerReview extends Model
{
    protected $table = 'buyer_reviews';

    protected $fillable = [
        'uuid',
        'order_id',
        'seller_user_id',
        'seller_profile_id',
        'buyer_user_id',
        'rating',
        'feedback_type',
        'title',
        'comment',
        'tags',
        'status',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'seller_user_id' => 'integer',
        'seller_profile_id' => 'integer',
        'buyer_user_id' => 'integer',
        'rating' => 'integer',
        'tags' => 'array',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}

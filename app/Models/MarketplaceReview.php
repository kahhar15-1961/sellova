<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceReview extends Model
{
    protected $table = 'marketplace_reviews';

    protected $fillable = [
        'uuid',
        'reviewer_id',
        'reviewer_role',
        'reviewed_id',
        'reviewed_role',
        'order_id',
        'rating',
        'feedback_type',
        'title',
        'comment',
        'tags',
        'review_images',
        'is_verified_order',
        'status',
        'moderated_by',
        'moderated_at',
        'moderation_note',
    ];

    protected $casts = [
        'reviewer_id' => 'integer',
        'reviewed_id' => 'integer',
        'order_id' => 'integer',
        'rating' => 'integer',
        'tags' => 'array',
        'review_images' => 'array',
        'is_verified_order' => 'boolean',
        'moderated_by' => 'integer',
        'moderated_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ReviewRating::class, 'marketplace_review_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class, 'marketplace_review_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewRating extends Model
{
    protected $table = 'review_ratings';

    protected $fillable = ['marketplace_review_id', 'category', 'rating'];

    protected $casts = [
        'marketplace_review_id' => 'integer',
        'rating' => 'integer',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(MarketplaceReview::class, 'marketplace_review_id');
    }
}

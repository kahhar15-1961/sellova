<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReport extends Model
{
    protected $table = 'review_reports';

    protected $fillable = [
        'marketplace_review_id',
        'reporter_id',
        'reason_code',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'marketplace_review_id' => 'integer',
        'reporter_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(MarketplaceReview::class, 'marketplace_review_id');
    }
}

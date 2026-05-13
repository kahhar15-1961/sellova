<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerProfile extends Model
{
    protected $table = 'buyer_profiles';

    protected $fillable = [
        'user_id',
        'display_name',
        'avatar_url',
        'verification_status',
        'kyc_status',
        'communication_rating',
        'payment_reliability_rating',
        'cooperation_rating',
        'public_badges_json',
        'last_active_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'communication_rating' => 'integer',
        'payment_reliability_rating' => 'integer',
        'cooperation_rating' => 'integer',
        'public_badges_json' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

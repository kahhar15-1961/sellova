<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustScoreSnapshot extends Model
{
    protected $table = 'trust_score_snapshots';

    protected $fillable = ['profile_type', 'profile_id', 'score', 'label', 'factors_json', 'calculated_at'];

    protected $casts = [
        'profile_id' => 'integer',
        'score' => 'integer',
        'factors_json' => 'array',
        'calculated_at' => 'datetime',
    ];
}

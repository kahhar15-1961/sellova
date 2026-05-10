<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycStatusHistory extends Model
{
    protected $table = 'kyc_status_histories';

    protected $fillable = [
        'uuid',
        'kyc_verification_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'reason_code',
        'note',
        'metadata_json',
    ];

    protected $casts = [
        'kyc_verification_id' => 'integer',
        'actor_user_id' => 'integer',
        'metadata_json' => 'array',
    ];

    public function kycVerification(): BelongsTo
    {
        return $this->belongsTo(KycVerification::class, 'kyc_verification_id');
    }
}

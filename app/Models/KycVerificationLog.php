<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycVerificationLog extends Model
{
    protected $table = 'kyc_verification_logs';

    protected $fillable = [
        'uuid',
        'kyc_verification_id',
        'provider_id',
        'direction',
        'event_type',
        'signature_status',
        'payload_json',
        'response_json',
    ];

    protected $casts = [
        'kyc_verification_id' => 'integer',
        'provider_id' => 'integer',
        'payload_json' => 'array',
        'response_json' => 'array',
    ];
}

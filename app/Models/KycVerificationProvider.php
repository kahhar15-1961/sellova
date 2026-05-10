<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycVerificationProvider extends Model
{
    protected $table = 'kyc_verification_providers';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'mode',
        'is_active',
        'config_json',
        'webhook_secret_encrypted',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config_json' => 'array',
        'webhook_secret_encrypted' => 'encrypted',
    ];
}

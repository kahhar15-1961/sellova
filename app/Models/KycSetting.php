<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycSetting extends Model
{
    protected $table = 'kyc_settings';

    protected $fillable = [
        'seller_type',
        'require_for_product_publish',
        'require_for_withdrawal',
        'required_documents_json',
        'expiry_months',
    ];

    protected $casts = [
        'require_for_product_publish' => 'boolean',
        'require_for_withdrawal' => 'boolean',
        'required_documents_json' => 'array',
        'expiry_months' => 'integer',
    ];
}

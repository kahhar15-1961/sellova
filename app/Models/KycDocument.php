<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kyc_verification_id
 * @property string $doc_type
 * @property string|null $storage_path
 * @property string|null $checksum_sha256
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KycVerification|null $kyc_verification
 */
class KycDocument extends Model
{
    protected $table = 'kyc_documents';

    protected $fillable = [
        'kyc_verification_id',
        'doc_type',
        'storage_path',
        'checksum_sha256',
        'status',
    ];

    protected $casts = [
        'kyc_verification_id' => 'integer',
        'doc_type' => 'string',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kyc_verification(): BelongsTo
    {
        return $this->belongsTo(KycVerification::class, 'kyc_verification_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $kyc_verification_id
 * @property string $doc_type
 * @property string|null $storage_path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int $file_size
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
        'uuid',
        'kyc_verification_id',
        'doc_type',
        'storage_path',
        'original_name',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'status',
        'review_notes',
    ];

    protected $casts = [
        'kyc_verification_id' => 'integer',
        'doc_type' => 'string',
        'file_size' => 'integer',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kyc_verification(): BelongsTo
    {
        return $this->belongsTo(KycVerification::class, 'kyc_verification_id');
    }
}

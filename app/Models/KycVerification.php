<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $seller_profile_id
 * @property string $status
 * @property string|null $provider_ref
 * @property int $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SellerProfile|null $seller_profile
 * @property-read \App\Models\User|null $reviewed_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\KycDocument> $kycDocuments
 */
class KycVerification extends Model
{
    protected $table = 'kyc_verifications';

    protected $fillable = [
        'uuid',
        'seller_profile_id',
        'status',
        'provider_ref',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'submitted_at',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'status' => 'string',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function reviewed_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class, 'kyc_verification_id');
    }
}

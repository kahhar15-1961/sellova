<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $seller_profile_id
 * @property int|null $submitted_by_user_id
 * @property string $status
 * @property array|null $personal_info_encrypted
 * @property array|null $business_info_encrypted
 * @property array|null $bank_info_encrypted
 * @property array|null $address_info_encrypted
 * @property string|null $provider_ref
 * @property int|null $provider_id
 * @property string|null $provider_session_id
 * @property string|null $provider_session_url
 * @property array|null $provider_result_json
 * @property int|null $assigned_to_user_id
 * @property Carbon|null $assigned_at
 * @property Carbon|null $sla_due_at
 * @property Carbon|null $sla_warning_sent_at
 * @property Carbon|null $escalated_at
 * @property string|null $escalation_reason
 * @property int $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SellerProfile|null $seller_profile
 * @property-read User|null $assigned_to_user
 * @property-read User|null $reviewed_by
 * @property-read Collection<int, KycDocument> $kycDocuments
 * @property-read Collection<int, KycVerificationNote> $notes
 */
class KycVerification extends Model
{
    protected $table = 'kyc_verifications';

    protected $fillable = [
        'uuid',
        'seller_profile_id',
        'submitted_by_user_id',
        'status',
        'personal_info_encrypted',
        'business_info_encrypted',
        'bank_info_encrypted',
        'address_info_encrypted',
        'provider_ref',
        'provider_id',
        'provider_session_id',
        'provider_session_url',
        'provider_result_json',
        'risk_level',
        'assigned_to_user_id',
        'assigned_at',
        'sla_due_at',
        'sla_warning_sent_at',
        'escalated_at',
        'escalation_reason',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'submitted_by_user_id' => 'integer',
        'status' => 'string',
        'personal_info_encrypted' => 'encrypted:array',
        'business_info_encrypted' => 'encrypted:array',
        'bank_info_encrypted' => 'encrypted:array',
        'address_info_encrypted' => 'encrypted:array',
        'provider_id' => 'integer',
        'provider_result_json' => 'array',
        'assigned_to_user_id' => 'integer',
        'assigned_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_warning_sent_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalation_reason' => 'string',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function assigned_to_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function reviewed_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class, 'kyc_verification_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(KycVerificationNote::class, 'kyc_verification_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(KycStatusHistory::class, 'kyc_verification_id');
    }
}

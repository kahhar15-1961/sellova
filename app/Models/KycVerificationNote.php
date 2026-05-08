<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $kyc_verification_id
 * @property int $user_id
 * @property bool $is_private
 * @property string $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KycVerificationNote extends Model
{
    protected $table = 'kyc_verification_notes';

    protected $fillable = [
        'uuid',
        'kyc_verification_id',
        'user_id',
        'is_private',
        'note',
    ];

    protected $casts = [
        'kyc_verification_id' => 'integer',
        'user_id' => 'integer',
        'is_private' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kyc_verification(): BelongsTo
    {
        return $this->belongsTo(KycVerification::class, 'kyc_verification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

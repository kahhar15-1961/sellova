<?php

namespace App\Models;

use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $dispute_case_id
 * @property int $submitted_by_user_id
 * @property string $evidence_type
 * @property string|null $content_text
 * @property string|null $storage_path
 * @property string|null $checksum_sha256
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\DisputeCase|null $dispute_case
 * @property-read \App\Models\User|null $submitted_by_user
 */
class DisputeEvidence extends Model
{
    use TransactionSensitive;

    protected $table = 'dispute_evidences';

    protected $fillable = [
        'uuid',
        'dispute_case_id',
        'submitted_by_user_id',
        'evidence_type',
        'content_text',
        'storage_path',
        'checksum_sha256',
        'submitted_at',
    ];

    protected $casts = [
        'dispute_case_id' => 'integer',
        'submitted_by_user_id' => 'integer',
        'evidence_type' => 'string',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function dispute_case(): BelongsTo
    {
        return $this->belongsTo(DisputeCase::class, 'dispute_case_id');
    }

    public function submitted_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}

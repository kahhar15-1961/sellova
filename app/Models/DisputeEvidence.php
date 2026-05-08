<?php

namespace App\Models;

use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $dispute_case_id
 * @property int|null $order_id
 * @property int $submitted_by_user_id
 * @property int|null $message_id
 * @property string|null $file_id
 * @property string|null $note
 * @property string $evidence_type
 * @property string|null $content_text
 * @property string|null $storage_path
 * @property string|null $checksum_sha256
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DisputeCase|null $dispute_case
 * @property-read User|null $submitted_by_user
 */
class DisputeEvidence extends Model
{
    use TransactionSensitive;

    protected $table = 'dispute_evidences';

    protected $fillable = [
        'uuid',
        'dispute_case_id',
        'order_id',
        'submitted_by_user_id',
        'message_id',
        'file_id',
        'note',
        'evidence_type',
        'content_text',
        'storage_path',
        'checksum_sha256',
        'submitted_at',
    ];

    protected $casts = [
        'dispute_case_id' => 'integer',
        'order_id' => 'integer',
        'submitted_by_user_id' => 'integer',
        'message_id' => 'integer',
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

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AdminActionApproval extends Model
{
    protected $table = 'admin_action_approvals';

    protected $fillable = [
        'uuid',
        'action_code',
        'target_type',
        'target_id',
        'proposed_payload_json',
        'requested_by_user_id',
        'approved_by_user_id',
        'status',
        'reason_code',
        'decision_reason',
        'requested_at',
        'decided_at',
    ];

    protected $casts = [
        'target_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'approved_by_user_id' => 'integer',
        'proposed_payload_json' => 'array',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requested_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approved_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AdminActionApprovalMessage::class, 'approval_id');
    }

    public function threadReads(): HasMany
    {
        return $this->hasMany(AdminActionApprovalThreadRead::class, 'approval_id');
    }

    /**
     * User IDs used for strict “read by everyone on thread” receipts:
     * message authors, the original requester, and anyone who has opened the thread (read cursor).
     *
     * @return list<int>
     */
    public function requiredReaderUserIds(): array
    {
        /** @var Collection<int, int> $ids */
        $ids = $this->messages()
            ->select('author_user_id')
            ->distinct()
            ->pluck('author_user_id');

        $ids = $ids->merge(
            $this->threadReads()->pluck('user_id'),
        );

        if ($this->requested_by_user_id !== null) {
            $ids->push((int) $this->requested_by_user_id);
        }

        return $ids
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }
}

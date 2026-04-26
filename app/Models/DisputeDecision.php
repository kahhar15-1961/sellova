<?php

namespace App\Models;

use App\Domain\Enums\DisputeResolutionOutcome;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $dispute_case_id
 * @property int $decided_by_user_id
 * @property DisputeResolutionOutcome $outcome
 * @property string $buyer_amount
 * @property string $seller_amount
 * @property string|null $currency
 * @property string|null $reason_code
 * @property string|null $notes
 * @property int $escrow_event_id
 * @property int $ledger_batch_id
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DisputeCase|null $dispute_case
 * @property-read User|null $decided_by_user
 * @property-read EscrowEvent|null $escrow_event
 * @property-read WalletLedgerBatch|null $ledger_batch
 */
class DisputeDecision extends Model
{
    use TransactionSensitive;

    protected $table = 'dispute_decisions';

    protected $fillable = [
        'uuid',
        'dispute_case_id',
        'decided_by_user_id',
        'outcome',
        'buyer_amount',
        'seller_amount',
        'currency',
        'reason_code',
        'notes',
        'escrow_event_id',
        'ledger_batch_id',
        'decided_at',
    ];

    protected $casts = [
        'dispute_case_id' => 'integer',
        'decided_by_user_id' => 'integer',
        'outcome' => DisputeResolutionOutcome::class,
        'buyer_amount' => 'decimal:4',
        'seller_amount' => 'decimal:4',
        'escrow_event_id' => 'integer',
        'ledger_batch_id' => 'integer',
        'decided_at' => 'datetime',
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

    public function decided_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function escrow_event(): BelongsTo
    {
        return $this->belongsTo(EscrowEvent::class, 'escrow_event_id');
    }

    public function ledger_batch(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerBatch::class, 'ledger_batch_id');
    }
}

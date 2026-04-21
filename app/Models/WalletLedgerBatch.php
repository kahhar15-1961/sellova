<?php

namespace App\Models;

use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\WalletLedgerBatchStatus;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property LedgerPostingEventName|null $event_name
 * @property string|null $reference_type
 * @property int $reference_id
 * @property int $idempotency_key_id
 * @property WalletLedgerBatchStatus $status
 * @property \Illuminate\Support\Carbon|null $posted_at
 * @property \Illuminate\Support\Carbon|null $reversed_at
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\IdempotencyKey|null $idempotency_key
 * @property-read \App\Models\User|null $created_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletLedgerEntry> $walletLedgerEntries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisputeDecision> $disputeDecisions
 */
class WalletLedgerBatch extends Model
{
    use TransactionSensitive;

    protected $table = 'wallet_ledger_batches';

    protected $fillable = [
        'uuid',
        'event_name',
        'reference_type',
        'reference_id',
        'idempotency_key_id',
        'status',
        'posted_at',
        'reversed_at',
        'created_by',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'idempotency_key_id' => 'integer',
        'event_name' => LedgerPostingEventName::class,
        'status' => WalletLedgerBatchStatus::class,
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function idempotency_key(): BelongsTo
    {
        return $this->belongsTo(IdempotencyKey::class, 'idempotency_key_id');
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function walletLedgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class, 'batch_id');
    }

    public function disputeDecisions(): HasMany
    {
        return $this->hasMany(DisputeDecision::class, 'ledger_batch_id');
    }
}

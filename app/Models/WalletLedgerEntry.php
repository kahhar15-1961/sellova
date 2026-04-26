<?php

namespace App\Models;

use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $batch_id
 * @property int $wallet_id
 * @property string $entry_side
 * @property string $entry_type
 * @property string $amount
 * @property string|null $currency
 * @property string $running_balance_after
 * @property string|null $reference_type
 * @property int $reference_id
 * @property int $counterparty_wallet_id
 * @property Carbon|null $occurred_at
 * @property int $reversal_of_entry_id
 * @property bool $is_reversal
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WalletLedgerBatch|null $batch
 * @property-read Wallet|null $wallet
 * @property-read Wallet|null $counterparty_wallet
 * @property-read WalletLedgerEntry|null $reversal_of_entry
 * @property-read Collection<int, WalletLedgerEntry> $walletLedgerEntries
 */
class WalletLedgerEntry extends Model
{
    use TransactionSensitive;

    protected $table = 'wallet_ledger_entries';

    protected $fillable = [
        'uuid',
        'batch_id',
        'wallet_id',
        'entry_side',
        'entry_type',
        'amount',
        'currency',
        'running_balance_after',
        'reference_type',
        'reference_id',
        'counterparty_wallet_id',
        'occurred_at',
        'reversal_of_entry_id',
        'is_reversal',
        'description',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'wallet_id' => 'integer',
        'entry_side' => WalletLedgerEntrySide::class,
        'entry_type' => WalletLedgerEntryType::class,
        'amount' => 'decimal:4',
        'running_balance_after' => 'decimal:4',
        'reference_id' => 'integer',
        'counterparty_wallet_id' => 'integer',
        'occurred_at' => 'datetime',
        'reversal_of_entry_id' => 'integer',
        'is_reversal' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */
    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new \LogicException('Wallet ledger entries are immutable and cannot be updated.');
        });

        static::deleting(static function (): void {
            throw new \LogicException('Wallet ledger entries are immutable and cannot be deleted.');
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerBatch::class, 'batch_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function counterparty_wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'counterparty_wallet_id');
    }

    public function reversal_of_entry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class, 'reversal_of_entry_id');
    }

    public function walletLedgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class, 'reversal_of_entry_id');
    }
}

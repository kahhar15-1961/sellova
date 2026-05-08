<?php

namespace App\Models;

use App\Domain\Enums\WalletAccountStatus;
use App\Domain\Enums\WalletType;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $user_id
 * @property WalletType $wallet_type
 * @property string|null $currency
 * @property WalletAccountStatus $status
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, WalletHold> $walletHolds
 * @property-read Collection<int, WalletTopUpRequest> $walletTopUpRequests
 * @property-read Collection<int, WalletLedgerEntry> $walletLedgerEntries
 * @property-read Collection<int, WalletLedgerEntry> $counterpartyLedgerEntries
 * @property-read Collection<int, WalletBalanceSnapshot> $walletBalanceSnapshots
 * @property-read Collection<int, WithdrawalRequest> $withdrawalRequests
 */
class Wallet extends Model
{
    use TransactionSensitive;

    protected $table = 'wallets';

    protected $fillable = [
        'uuid',
        'user_id',
        'wallet_type',
        'currency',
        'status',
        'version',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'wallet_type' => WalletType::class,
        'status' => WalletAccountStatus::class,
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function walletHolds(): HasMany
    {
        return $this->hasMany(WalletHold::class, 'wallet_id');
    }

    public function walletTopUpRequests(): HasMany
    {
        return $this->hasMany(WalletTopUpRequest::class, 'wallet_id');
    }

    public function walletLedgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class, 'wallet_id');
    }

    public function counterpartyLedgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class, 'counterparty_wallet_id');
    }

    public function walletBalanceSnapshots(): HasMany
    {
        return $this->hasMany(WalletBalanceSnapshot::class, 'wallet_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'wallet_id');
    }
}

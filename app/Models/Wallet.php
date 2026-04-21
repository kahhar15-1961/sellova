<?php

namespace App\Models;

use App\Domain\Enums\WalletAccountStatus;
use App\Domain\Enums\WalletType;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $user_id
 * @property WalletType $wallet_type
 * @property string|null $currency
 * @property WalletAccountStatus $status
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletHold> $walletHolds
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletLedgerEntry> $walletLedgerEntries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletLedgerEntry> $counterpartyLedgerEntries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletBalanceSnapshot> $walletBalanceSnapshots
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WithdrawalRequest> $withdrawalRequests
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

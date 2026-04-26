<?php

namespace App\Models;

use App\Domain\Enums\WalletHoldStatus;
use App\Domain\Enums\WalletHoldType;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $wallet_id
 * @property string $hold_type
 * @property string|null $reference_type
 * @property int $reference_id
 * @property string $amount
 * @property string|null $currency
 * @property WalletHoldStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet|null $wallet
 * @property-read Collection<int, WithdrawalRequest> $withdrawalRequests
 */
class WalletHold extends Model
{
    use TransactionSensitive;

    protected $table = 'wallet_holds';

    protected $fillable = [
        'uuid',
        'wallet_id',
        'hold_type',
        'reference_type',
        'reference_id',
        'amount',
        'currency',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'wallet_id' => 'integer',
        'hold_type' => WalletHoldType::class,
        'reference_id' => 'integer',
        'amount' => 'decimal:4',
        'status' => WalletHoldStatus::class,
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'hold_id');
    }
}

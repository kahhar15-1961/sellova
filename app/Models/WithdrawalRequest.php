<?php

namespace App\Models;

use App\Domain\Enums\WithdrawalRequestStatus;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string $idempotency_key
 * @property int $seller_profile_id
 * @property int $wallet_id
 * @property string $status
 * @property string $requested_amount
 * @property string $fee_amount
 * @property string $net_payout_amount
 * @property string|null $currency
 * @property int $hold_id
 * @property int $reviewed_by
 * @property int|null $assigned_to_user_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $escalated_at
 * @property string|null $escalation_reason
 * @property string|null $reject_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SellerProfile|null $seller_profile
 * @property-read Wallet|null $wallet
 * @property-read WalletHold|null $hold
 * @property-read User|null $reviewed_by
 * @property-read User|null $assigned_to_user
 * @property-read Collection<int, WithdrawalTransaction> $withdrawalTransactions
 */
class WithdrawalRequest extends Model
{
    use TransactionSensitive;

    protected $table = 'withdrawal_requests';

    protected $fillable = [
        'uuid',
        'idempotency_key',
        'seller_profile_id',
        'wallet_id',
        'status',
        'requested_amount',
        'fee_amount',
        'net_payout_amount',
        'currency',
        'hold_id',
        'reviewed_by',
        'assigned_to_user_id',
        'reviewed_at',
        'assigned_at',
        'escalated_at',
        'escalation_reason',
        'reject_reason',
    ];

    protected $casts = [
        'idempotency_key' => 'string',
        'seller_profile_id' => 'integer',
        'wallet_id' => 'integer',
        'status' => WithdrawalRequestStatus::class,
        'requested_amount' => 'decimal:4',
        'fee_amount' => 'decimal:4',
        'net_payout_amount' => 'decimal:4',
        'hold_id' => 'integer',
        'reviewed_by' => 'integer',
        'assigned_to_user_id' => 'integer',
        'reviewed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'escalated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */
    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(WalletHold::class, 'hold_id');
    }

    public function reviewed_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assigned_to_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function withdrawalTransactions(): HasMany
    {
        return $this->hasMany(WithdrawalTransaction::class, 'withdrawal_request_id');
    }
}

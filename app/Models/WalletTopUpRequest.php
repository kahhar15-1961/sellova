<?php

namespace App\Models;

use App\Domain\Enums\WalletTopUpRequestStatus;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Wallet;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string $idempotency_key
 * @property int $wallet_id
 * @property int $requested_by_user_id
 * @property WalletTopUpRequestStatus $status
 * @property string $requested_amount
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property string|null $payment_proof_url
 * @property string|null $currency
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int|null $ledger_batch_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WalletTopUpRequest extends Model
{
    use TransactionSensitive;

    protected $table = 'wallet_top_up_requests';

    protected $fillable = [
        'uuid',
        'idempotency_key',
        'wallet_id',
        'requested_by_user_id',
        'status',
        'requested_amount',
        'payment_method',
        'payment_reference',
        'payment_proof_url',
        'currency',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'ledger_batch_id',
    ];

    protected $casts = [
        'wallet_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'status' => WalletTopUpRequestStatus::class,
        'requested_amount' => 'decimal:4',
        'payment_method' => 'string',
        'payment_reference' => 'string',
        'reviewed_by_user_id' => 'integer',
        'reviewed_at' => 'datetime',
        'ledger_batch_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function requested_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewed_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}

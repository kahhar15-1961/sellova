<?php

namespace App\Models;

use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $withdrawal_request_id
 * @property string|null $provider
 * @property string|null $provider_transfer_ref
 * @property int $attempt_no
 * @property string $status
 * @property string $amount
 * @property string|null $currency
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\WithdrawalRequest|null $withdrawal_request
 */
class WithdrawalTransaction extends Model
{
    use TransactionSensitive;

    protected $table = 'withdrawal_transactions';

    protected $fillable = [
        'uuid',
        'withdrawal_request_id',
        'provider',
        'provider_transfer_ref',
        'attempt_no',
        'status',
        'amount',
        'currency',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'withdrawal_request_id' => 'integer',
        'attempt_no' => 'integer',
        'status' => 'string',
        'amount' => 'decimal:4',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function withdrawal_request(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_request_id');
    }
}

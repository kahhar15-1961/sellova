<?php

namespace App\Models;

use App\Domain\Enums\IdempotencyKeyStatus;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $key
 * @property string|null $scope
 * @property string|null $request_hash
 * @property string|null $response_hash
 * @property IdempotencyKeyStatus $status
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EscrowEvent> $escrowEvents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletLedgerBatch> $walletLedgerBatches
 */
class IdempotencyKey extends Model
{
    use TransactionSensitive;

    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key',
        'scope',
        'request_hash',
        'response_hash',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'status' => IdempotencyKeyStatus::class,
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function escrowEvents(): HasMany
    {
        return $this->hasMany(EscrowEvent::class, 'idempotency_key_id');
    }

    public function walletLedgerBatches(): HasMany
    {
        return $this->hasMany(WalletLedgerBatch::class, 'idempotency_key_id');
    }
}

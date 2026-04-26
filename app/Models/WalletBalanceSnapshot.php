<?php

namespace App\Models;

use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property Carbon|null $as_of
 * @property string $available_balance
 * @property string $held_balance
 * @property Carbon|null $created_at
 * @property-read Wallet|null $wallet
 */
class WalletBalanceSnapshot extends Model
{
    use TransactionSensitive;

    protected $table = 'wallet_balance_snapshots';

    protected $fillable = [
        'wallet_id',
        'as_of',
        'available_balance',
        'held_balance',
    ];

    protected $casts = [
        'wallet_id' => 'integer',
        'as_of' => 'datetime',
        'available_balance' => 'decimal:4',
        'held_balance' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }
}

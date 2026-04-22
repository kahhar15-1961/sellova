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
 * @property int $order_id
 * @property string|null $from_state
 * @property string|null $to_state
 * @property string|null $reason_code
 * @property int $actor_user_id
 * @property string|null $correlation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\Order|null $order
 * @property-read \App\Models\User|null $actor_user
 */
class OrderStateTransition extends Model
{
    use TransactionSensitive;

    public $timestamps = false;

    protected $table = 'order_state_transitions';

    protected $fillable = [
        'order_id',
        'from_state',
        'to_state',
        'reason_code',
        'actor_user_id',
        'correlation_id',
        'created_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'actor_user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function actor_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

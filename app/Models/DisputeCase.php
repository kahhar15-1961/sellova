<?php

namespace App\Models;

use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\DisputeResolutionOutcome;
use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $order_id
 * @property int $order_item_id
 * @property int $opened_by_user_id
 * @property DisputeCaseStatus $status
 * @property DisputeResolutionOutcome|null $resolution_outcome
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $resolution_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order|null $order
 * @property-read \App\Models\OrderItem|null $order_item
 * @property-read \App\Models\User|null $opened_by_user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisputeEvidence> $disputeEvidences
 * @property-read \App\Models\DisputeDecision|null $disputeDecision
 */
class DisputeCase extends Model
{
    use TransactionSensitive;

    protected $table = 'dispute_cases';

    protected $fillable = [
        'uuid',
        'order_id',
        'order_item_id',
        'opened_by_user_id',
        'status',
        'resolution_outcome',
        'opened_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'order_item_id' => 'integer',
        'opened_by_user_id' => 'integer',
        'status' => DisputeCaseStatus::class,
        'resolution_outcome' => DisputeResolutionOutcome::class,
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function order_item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function opened_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function disputeEvidences(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class, 'dispute_case_id');
    }

    public function disputeDecision(): HasOne
    {
        return $this->hasOne(DisputeDecision::class, 'dispute_case_id');
    }
}

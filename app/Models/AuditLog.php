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
 * @property int $actor_user_id
 * @property string|null $action
 * @property string|null $target_type
 * @property int $target_id
 * @property array $before_json
 * @property array $after_json
 * @property string|null $reason_code
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $correlation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\User|null $actor_user
 */
class AuditLog extends Model
{
    use TransactionSensitive;

    protected $table = 'audit_logs';

    protected $fillable = [
        'uuid',
        'actor_user_id',
        'action',
        'target_type',
        'target_id',
        'before_json',
        'after_json',
        'reason_code',
        'ip_address',
        'user_agent',
        'correlation_id',
    ];

    protected $casts = [
        'actor_user_id' => 'integer',
        'target_id' => 'integer',
        'before_json' => 'array',
        'after_json' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function actor_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

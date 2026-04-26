<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property int $assigned_by
 * @property Carbon|null $created_at
 * @property-read User|null $user
 * @property-read Role|null $role
 * @property-read User|null $assigned_by
 */
class UserRole extends Model
{
    protected $table = 'user_roles';

    protected $fillable = [
        'user_id',
        'role_id',
        'assigned_by',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'role_id' => 'integer',
        'assigned_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function assigned_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

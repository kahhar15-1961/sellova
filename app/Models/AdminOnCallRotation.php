<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminOnCallRotation extends Model
{
    protected $table = 'admin_on_call_rotations';

    protected $fillable = [
        'role_code',
        'user_id',
        'weekday',
        'start_hour',
        'end_hour',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'weekday' => 'integer',
        'start_hour' => 'integer',
        'end_hour' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

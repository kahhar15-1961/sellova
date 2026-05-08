<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $platform
 * @property string $device_token
 * @property string|null $device_name
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 */
class PushDevice extends Model
{
    protected $table = 'push_devices';

    protected $fillable = [
        'user_id',
        'platform',
        'device_token',
        'device_name',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'platform' => 'string',
        'device_token' => 'string',
        'device_name' => 'string',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

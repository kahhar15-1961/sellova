<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $user_id
 * @property string $channel
 * @property string|null $template_code
 * @property array $payload_json
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 */
class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'uuid',
        'user_id',
        'channel',
        'template_code',
        'payload_json',
        'status',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'channel' => 'string',
        'payload_json' => 'array',
        'status' => 'string',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

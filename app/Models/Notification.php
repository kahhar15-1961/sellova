<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $user_id
 * @property string $channel
 * @property string|null $template_code
 * @property array $payload_json
 * @property string $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
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

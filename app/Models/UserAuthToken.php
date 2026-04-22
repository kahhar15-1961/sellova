<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $token_family
 * @property string $token_hash
 * @property string $kind
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read User|null $user
 */
class UserAuthToken extends Model
{
    public const KIND_ACCESS = 'access';

    public const KIND_REFRESH = 'refresh';

    public $timestamps = false;

    protected $table = 'user_auth_tokens';

    protected $fillable = [
        'uuid',
        'user_id',
        'token_family',
        'token_hash',
        'kind',
        'expires_at',
        'revoked_at',
        'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

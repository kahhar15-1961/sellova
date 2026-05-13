<?php

namespace App\Models;

use App\Events\UserNotificationCreated;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $user_id
 * @property string $channel
 * @property string|null $type
 * @property string|null $template_code
 * @property string|null $title
 * @property string|null $message
 * @property string|null $icon
 * @property string|null $color
 * @property string|null $action_url
 * @property array $payload_json
 * @property array|null $metadata_json
 * @property string|null $user_role
 * @property string|null $priority
 * @property string $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class Notification extends Model
{
    public const ROLE_BUYER = 'buyer';

    public const ROLE_SELLER = 'seller';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_ALL = 'all';

    protected $table = 'notifications';

    protected $fillable = [
        'uuid',
        'user_id',
        'user_role',
        'channel',
        'type',
        'template_code',
        'title',
        'message',
        'icon',
        'color',
        'action_url',
        'payload_json',
        'metadata_json',
        'priority',
        'status',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'channel' => 'string',
        'type' => 'string',
        'payload_json' => 'array',
        'metadata_json' => 'array',
        'user_role' => 'string',
        'priority' => 'string',
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

    protected static function booted(): void
    {
        static::creating(static function (self $notification): void {
            $notification->normalizePanelAttributes();
        });

        static::created(static function (self $notification): void {
            if ($notification->channel !== 'in_app') {
                return;
            }

            $presented = NotificationPresenter::present($notification);

            UserNotificationCreated::dispatch(
                (int) $notification->user_id,
                (string) $presented['role'],
                $presented,
                self::unreadCountForRole((int) $notification->user_id, (string) $presented['role']),
            );
        });
    }

    public function scopeForPanel(Builder $query, int $userId, string $role): Builder
    {
        $role = self::normalizeRole($role);

        return $query
            ->where('user_id', $userId)
            ->where('channel', 'in_app')
            ->where(function (Builder $scoped) use ($role): void {
                $scoped
                    ->where('user_role', $role)
                    ->orWhere('user_role', self::ROLE_ALL)
                    ->orWhere(function (Builder $legacy) use ($role): void {
                        $legacy->whereNull('user_role');

                        if ($role === self::ROLE_SELLER) {
                            $legacy->where('action_url', 'like', '/seller/%');

                            return;
                        }

                        $legacy->where(function (Builder $buyerQuery): void {
                            $buyerQuery->whereNull('action_url')->orWhere('action_url', 'not like', '/seller/%');
                        });
                    });
            });
    }

    public static function unreadCountForRole(int $userId, string $role): int
    {
        return self::query()
            ->forPanel($userId, $role)
            ->whereNull('read_at')
            ->count();
    }

    public static function normalizeRole(string $role): string
    {
        $normalized = Str::lower(trim($role));

        return in_array($normalized, [self::ROLE_BUYER, self::ROLE_SELLER, self::ROLE_ADMIN, self::ROLE_ALL], true)
            ? $normalized
            : self::ROLE_BUYER;
    }

    private function normalizePanelAttributes(): void
    {
        $payload = is_array($this->payload_json ?? null) ? $this->payload_json : [];
        $role = NotificationPresenter::inferRoleFromMetadata($payload);

        $this->type ??= $this->template_code ?: $this->channel;
        $this->title ??= isset($payload['title']) ? (string) $payload['title'] : (isset($payload['subject']) ? (string) $payload['subject'] : null);
        $this->message ??= isset($payload['body']) ? (string) $payload['body'] : (isset($payload['message']) ? (string) $payload['message'] : null);
        $this->icon ??= isset($payload['icon']) ? (string) $payload['icon'] : null;
        $this->color ??= isset($payload['color']) ? (string) $payload['color'] : null;
        $this->action_url ??= isset($payload['href']) ? (string) $payload['href'] : (isset($payload['action_url']) ? (string) $payload['action_url'] : null);
        $this->metadata_json ??= $payload;
        $this->priority ??= isset($payload['priority']) ? (string) $payload['priority'] : 'normal';
        $this->user_role ??= $role;
        $this->status ??= $this->channel === 'in_app' ? 'sent' : 'queued';
        $this->uuid ??= (string) Str::uuid();
    }
}

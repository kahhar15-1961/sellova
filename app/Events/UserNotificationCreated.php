<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $notification
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
        public readonly array $notification,
        public readonly int $unreadCount,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('App.Models.User.'.$this->userId),
            new PrivateChannel('user.'.$this->userId),
        ];

        $accountId = (int) ($this->notification['recipient_account_id'] ?? 0);
        if ($this->role === 'buyer') {
            $channels[] = new PrivateChannel('buyer.'.($accountId > 0 ? $accountId : $this->userId));
        }
        if ($this->role === 'seller' && $accountId > 0) {
            $channels[] = new PrivateChannel('seller.'.$accountId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'role' => $this->role,
            'notification' => $this->notification,
            'unread_count' => $this->unreadCount,
        ];
    }
}

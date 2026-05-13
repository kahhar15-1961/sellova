<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserNotificationStateChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.state.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

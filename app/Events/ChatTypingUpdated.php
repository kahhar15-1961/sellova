<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ChatTypingUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $threadId,
        public readonly int $userId,
        public readonly string $name,
        public readonly bool $typing,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.thread.'.$this->threadId);
    }

    public function broadcastAs(): string
    {
        return 'chat.typing.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'user_id' => $this->userId,
            'name' => $this->name,
            'typing' => $this->typing,
        ];
    }
}


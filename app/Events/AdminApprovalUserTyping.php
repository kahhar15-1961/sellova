<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AdminApprovalUserTyping implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $approvalId,
        public readonly int $userId,
        public readonly string $name,
        public readonly bool $typing,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('admin.approval.'.$this->approvalId);
    }

    public function broadcastAs(): string
    {
        return 'admin.approval.user.typing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'approval_id' => $this->approvalId,
            'user_id' => $this->userId,
            'name' => $this->name,
            'typing' => $this->typing,
        ];
    }
}

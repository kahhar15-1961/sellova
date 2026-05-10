<?php

namespace App\Services\TimeoutAutomation;

use App\Models\Notification;
use App\Services\PushNotification\PushNotificationService;
use Illuminate\Support\Str;

final class TimeoutNotificationService
{
    public function __construct(private readonly PushNotificationService $push = new PushNotificationService())
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function notify(int $userId, string $template, string $title, string $body, array $payload = []): void
    {
        foreach (['in_app', 'email'] as $channel) {
            $notification = Notification::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'user_role' => $payload['role'] ?? null,
                'channel' => $channel,
                'template_code' => $template,
                'payload_json' => array_merge($payload, [
                    'title' => $title,
                    'body' => $body,
                    'sms_hook_ready' => true,
                ]),
                'status' => $channel === 'email' ? 'queued' : 'sent',
                'sent_at' => $channel === 'in_app' ? now() : null,
            ]);
        }

        $this->push->sendToUser($userId, array_merge($payload, [
            'title' => $title,
            'body' => $body,
            'template_code' => $template,
        ]));
    }
}

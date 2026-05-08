<?php

namespace App\Services\TimeoutAutomation;

use App\Events\UserNotificationCreated;
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

            if ($channel === 'in_app') {
                UserNotificationCreated::dispatch($userId, [
                    'id' => (int) $notification->id,
                    'uuid' => (string) $notification->uuid,
                    'channel' => 'in_app',
                    'template_code' => $template,
                    'title' => $title,
                    'body' => $body,
                    'is_read' => false,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ], Notification::query()->where('user_id', $userId)->whereNull('read_at')->count());
            }
        }

        $this->push->sendToUser($userId, array_merge($payload, [
            'title' => $title,
            'body' => $body,
            'template_code' => $template,
        ]));
    }
}


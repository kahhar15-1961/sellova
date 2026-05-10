<?php

namespace App\Services\Notification;

use App\Models\Notification as NotificationModel;
use App\Services\PushNotification\PushNotificationService;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private readonly PushNotificationService $push = new PushNotificationService(),
    ) {
    }

    public function notify(int $userId, string $template, string $title, string $body, array $payload = []): void
    {
        $notification = NotificationModel::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'user_role' => $payload['role'] ?? null,
            'channel' => 'in_app',
            'template_code' => $template,
            'payload_json' => array_merge($payload, [
                'title' => $title,
                'body' => $body,
            ]),
            'read_at' => null,
        ]);

        $this->push->sendToUser($userId, [
            'id' => (int) $notification->id,
            'title' => $title,
            'body' => $body,
            'kind' => $template,
            'payload' => $notification->payload_json,
        ]);
    }
}

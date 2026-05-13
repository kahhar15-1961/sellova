<?php

namespace App\Services\TimeoutAutomation;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\PushNotification\PushNotificationService;
use Illuminate\Support\Facades\Mail;
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
        $role = in_array((string) ($payload['recipient_context'] ?? $payload['recipient_role'] ?? $payload['role'] ?? ''), ['seller', 'admin'], true)
            ? (string) ($payload['recipient_context'] ?? $payload['recipient_role'] ?? $payload['role'])
            : 'buyer';
        $entityId = (int) ($payload['context_entity_id'] ?? $payload['order_id'] ?? 0);
        $payload = array_merge($payload, [
            'role' => $role,
            'recipient_user_id' => $userId,
            'recipient_context' => $role,
            'recipient_role' => $role,
            'notification_type' => $template,
            'context_entity_type' => $payload['context_entity_type'] ?? ($entityId > 0 ? 'order' : 'notification'),
            'context_entity_id' => $entityId ?: null,
            'context_route_name' => $payload['context_route_name'] ?? ($entityId > 0 ? $role.'.orders.show' : null),
            'action_url' => $payload['action_url'] ?? $payload['href'] ?? ($entityId > 0 ? ($role === 'seller' ? "/seller/orders/{$entityId}" : "/buyer/orders/{$entityId}") : null),
        ]);

        foreach (['in_app', 'email'] as $channel) {
            $notification = Notification::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'user_role' => $role,
                'channel' => $channel,
                'template_code' => $template,
                'type' => $template,
                'title' => $title,
                'message' => $body,
                'action_url' => $payload['action_url'] ?? null,
                'metadata_json' => array_merge($payload, [
                    'title' => $title,
                    'body' => $body,
                ]),
                'payload_json' => array_merge($payload, [
                    'title' => $title,
                    'body' => $body,
                    'sms_hook_ready' => true,
                ]),
                'status' => $channel === 'email' ? 'queued' : 'sent',
                'sent_at' => $channel === 'in_app' ? now() : null,
            ]);
        }

        $this->sendEmail($userId, $title, $body, (string) ($payload['action_url'] ?? ''));

        $this->push->sendToUser($userId, array_merge($payload, [
            'title' => $title,
            'body' => $body,
            'template_code' => $template,
        ]));
    }

    private function sendEmail(int $userId, string $title, string $body, string $actionUrl): void
    {
        $user = User::query()->find($userId);
        if (! $user instanceof User || trim((string) $user->email) === '') {
            return;
        }
        $preferences = UserNotificationPreference::query()->where('user_id', $userId)->first();
        if ($preferences instanceof UserNotificationPreference && ! $preferences->email_enabled) {
            return;
        }
        try {
            Mail::raw($body."\n\nOpen: ".$actionUrl, static function ($message) use ($user, $title): void {
                $message->to((string) $user->email)->subject($title);
            });
        } catch (\Throwable) {
            // Email transport failures must not block realtime notifications.
        }
    }
}

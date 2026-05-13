<?php

namespace App\Services\Notification;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\PushNotification\PushNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private readonly PushNotificationService $push = new PushNotificationService(),
    ) {
    }

    public function notify(int $userId, string $template, string $title, string $body, array $payload = []): void
    {
        $role = $this->normalizeContext((string) ($payload['recipient_context'] ?? $payload['recipient_role'] ?? $payload['role'] ?? 'buyer'));
        $entityType = (string) ($payload['context_entity_type'] ?? (isset($payload['order_id']) ? 'order' : 'notification'));
        $entityId = (int) ($payload['context_entity_id'] ?? $payload['order_id'] ?? 0);
        $actionUrl = (string) ($payload['action_url'] ?? $payload['href'] ?? '');

        if ($actionUrl === '' && $entityType === 'order' && $entityId > 0) {
            $actionUrl = $role === 'seller' ? "/seller/orders/{$entityId}" : "/buyer/orders/{$entityId}";
        }

        $contextRouteName = (string) ($payload['context_route_name'] ?? '');
        if ($contextRouteName === '' && $entityType === 'order') {
            $contextRouteName = $role.'.orders.show';
        }

        $metadata = array_merge($payload, [
            'recipient_user_id' => $userId,
            'recipient_account_id' => $payload['recipient_account_id'] ?? $this->recipientAccountId($userId, $role),
            'recipient_role' => $role,
            'recipient_context' => $role,
            'actor_user_id' => $payload['actor_user_id'] ?? $payload['changed_by_user_id'] ?? null,
            'actor_role' => $payload['actor_role'] ?? null,
            'notification_type' => $template,
            'context_route_name' => $contextRouteName,
            'context_entity_type' => $entityType,
            'context_entity_id' => $entityId ?: null,
            'href' => $actionUrl,
            'action_url' => $actionUrl,
            'title' => $title,
            'body' => $body,
            'message' => $body,
        ]);

        $notification = NotificationModel::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'user_role' => $role,
            'channel' => 'in_app',
            'template_code' => $template,
            'type' => $template,
            'title' => $title,
            'message' => $body,
            'action_url' => $actionUrl,
            'metadata_json' => $metadata,
            'payload_json' => $metadata,
            'read_at' => null,
        ]);

        $this->push->sendToUser($userId, [
            'id' => (int) $notification->id,
            'title' => $title,
            'body' => $body,
            'kind' => $template,
            'payload' => $notification->payload_json,
        ]);

        $this->sendEmail($userId, $title, $body, $metadata);
    }

    private function normalizeContext(string $context): string
    {
        $context = Str::lower(trim($context));

        return in_array($context, ['buyer', 'seller', 'admin'], true) ? $context : 'buyer';
    }

    private function recipientAccountId(int $userId, string $role): ?int
    {
        if ($role !== 'seller') {
            return $userId;
        }

        $user = User::query()->with('sellerProfile:id,user_id')->find($userId);

        return $user?->sellerProfile?->id ? (int) $user->sellerProfile->id : null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function sendEmail(int $userId, string $title, string $body, array $metadata): void
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
            Mail::raw($body."\n\nOpen: ".(string) ($metadata['action_url'] ?? ''), static function ($message) use ($user, $title): void {
                $message->to((string) $user->email)->subject($title);
            });
        } catch (\Throwable) {
            // Email should not block in-app or realtime delivery.
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\Notification;
use Illuminate\Support\Str;

final class NotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Notification $notification): array
    {
        $metadata = is_array($notification->metadata_json ?? null)
            ? $notification->metadata_json
            : (is_array($notification->payload_json ?? null) ? $notification->payload_json : []);

        $role = self::normalizeRole(
            (string) ($metadata['recipient_context'] ?? $metadata['recipient_role'] ?? $notification->user_role ?? ($metadata['role'] ?? '')),
            self::actionUrl($notification, $metadata),
        );
        $actionUrl = self::sanitizeActionUrl(self::actionUrl($notification, $metadata), $role);
        $type = self::value(
            $notification->type,
            $notification->template_code,
            isset($metadata['type']) ? (string) $metadata['type'] : null,
            'notification',
        );
        $icon = self::value($notification->icon, isset($metadata['icon']) ? (string) $metadata['icon'] : null, self::defaultIcon($type));
        $color = self::value($notification->color, isset($metadata['color']) ? (string) $metadata['color'] : null, self::defaultColor($type));
        $title = self::value(
            $notification->title,
            isset($metadata['title']) ? (string) $metadata['title'] : null,
            isset($metadata['subject']) ? (string) $metadata['subject'] : null,
            Str::headline(str_replace(['.', '_', '-'], ' ', $type)),
        );
        $message = self::value(
            $notification->message,
            isset($metadata['body']) ? (string) $metadata['body'] : null,
            isset($metadata['message']) ? (string) $metadata['message'] : null,
            '',
        );
        $priority = self::value($notification->priority, isset($metadata['priority']) ? (string) $metadata['priority'] : null, 'normal');
        $isRead = $notification->read_at !== null || (string) $notification->status === 'read';

        return [
            'id' => (int) $notification->id,
            'uuid' => (string) ($notification->uuid ?? ''),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'body' => $message,
            'icon' => $icon,
            'color' => $color,
            'action_url' => $actionUrl,
            'href' => $actionUrl,
            'is_read' => $isRead,
            'read' => $isRead,
            'time_ago' => (string) ($notification->created_at?->diffForHumans() ?? 'Just now'),
            'time' => (string) ($notification->created_at?->diffForHumans() ?? 'Just now'),
            'created_at' => $notification->created_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
            'metadata' => $metadata,
            'payload' => $metadata,
            'recipient_user_id' => (int) ($metadata['recipient_user_id'] ?? $notification->user_id),
            'recipient_account_id' => $metadata['recipient_account_id'] ?? null,
            'recipient_role' => (string) ($metadata['recipient_role'] ?? $role),
            'recipient_context' => (string) ($metadata['recipient_context'] ?? $role),
            'actor_user_id' => $metadata['actor_user_id'] ?? null,
            'actor_role' => $metadata['actor_role'] ?? null,
            'notification_type' => (string) ($metadata['notification_type'] ?? $type),
            'context_route_name' => $metadata['context_route_name'] ?? null,
            'context_entity_type' => $metadata['context_entity_type'] ?? null,
            'context_entity_id' => $metadata['context_entity_id'] ?? ($metadata['order_id'] ?? null),
            'priority' => $priority,
            'channel' => (string) $notification->channel,
            'role' => $role,
            'context' => $role,
            'kind' => $type,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function inferRoleFromMetadata(array $metadata): string
    {
        return self::normalizeRole(
            (string) ($metadata['recipient_context'] ?? $metadata['recipient_role'] ?? $metadata['role'] ?? ''),
            isset($metadata['href']) ? (string) $metadata['href'] : null,
        );
    }

    private static function actionUrl(Notification $notification, array $metadata): ?string
    {
        return self::value(
            $notification->action_url,
            isset($metadata['href']) ? (string) $metadata['href'] : null,
            isset($metadata['action_url']) ? (string) $metadata['action_url'] : null,
        );
    }

    private static function normalizeRole(string $role, ?string $actionUrl): string
    {
        $normalized = Str::of($role)->lower()->trim()->toString();
        if (in_array($normalized, ['buyer', 'seller', 'admin', 'all'], true)) {
            return $normalized;
        }

        return match (true) {
            str_starts_with((string) $actionUrl, '/seller/') => 'seller',
            str_starts_with((string) $actionUrl, '/admin/') => 'admin',
            default => 'buyer',
        };
    }

    private static function sanitizeActionUrl(?string $actionUrl, string $role): string
    {
        $fallback = match ($role) {
            'seller' => '/seller/notifications',
            'admin' => '/admin',
            default => '/notifications',
        };
        $url = trim((string) $actionUrl);

        if ($url === '' || str_starts_with($url, '//') || preg_match('/^\s*(javascript|data):/i', $url)) {
            return $fallback;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return $fallback;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $appHost !== null && strcasecmp((string) $host, (string) $appHost) === 0
            ? $url
            : $fallback;
    }

    private static function defaultIcon(string $type): string
    {
        return match (true) {
            str_contains($type, 'order') => 'package',
            str_contains($type, 'fund'), str_contains($type, 'escrow'), str_contains($type, 'withdraw'), str_contains($type, 'top_up') => 'shield-check',
            str_contains($type, 'message'), str_contains($type, 'chat') => 'message-square-text',
            str_contains($type, 'sale'), str_contains($type, 'promo'), str_contains($type, 'coupon') => 'zap',
            str_contains($type, 'kyc') => 'badge-check',
            str_contains($type, 'refund'), str_contains($type, 'return') => 'receipt-text',
            str_contains($type, 'dispute') => 'alert-circle',
            str_contains($type, 'product') => 'shopping-bag',
            default => 'bell',
        };
    }

    private static function defaultColor(string $type): string
    {
        return match (true) {
            str_contains($type, 'order'), str_contains($type, 'product') => 'emerald',
            str_contains($type, 'fund'), str_contains($type, 'escrow'), str_contains($type, 'withdraw'), str_contains($type, 'top_up') => 'indigo',
            str_contains($type, 'message'), str_contains($type, 'chat') => 'sky',
            str_contains($type, 'sale'), str_contains($type, 'promo'), str_contains($type, 'coupon') => 'rose',
            str_contains($type, 'kyc') => 'amber',
            str_contains($type, 'refund'), str_contains($type, 'return') => 'teal',
            str_contains($type, 'dispute') => 'orange',
            default => 'slate',
        };
    }

    private static function value(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}

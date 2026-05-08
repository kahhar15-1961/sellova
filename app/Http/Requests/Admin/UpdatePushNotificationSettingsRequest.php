<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Symfony\Component\HttpFoundation\Request;

final class UpdatePushNotificationSettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return [
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'provider' => trim((string) ($payload['provider'] ?? 'fcm')),
            'fcm_project_id' => trim((string) ($payload['fcm_project_id'] ?? '')),
            'fcm_client_email' => trim((string) ($payload['fcm_client_email'] ?? '')),
            'fcm_private_key' => trim((string) ($payload['fcm_private_key'] ?? '')),
            'android_channel_id' => trim((string) ($payload['android_channel_id'] ?? 'sellova-default')),
            'android_channel_name' => trim((string) ($payload['android_channel_name'] ?? 'Sellova')),
            'android_channel_description' => trim((string) ($payload['android_channel_description'] ?? 'Order, wallet, and support alerts.')),
        ];
    }
}

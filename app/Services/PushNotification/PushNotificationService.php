<?php

declare(strict_types=1);

namespace App\Services\PushNotification;

use App\Models\PushDevice;
use App\Models\PushNotificationSetting;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class PushNotificationService
{
    public function __construct(
        private readonly PushNotificationSettingsService $settingsService = new PushNotificationSettingsService(),
    ) {}

    /**
     * @param array<string, mixed> $notification
     */
    public function sendToUser(int $userId, array $notification): void
    {
        $settings = $this->settingsService->current();
        if (! $settings->enabled || $settings->provider !== 'fcm') {
            return;
        }

        $devices = PushDevice::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNotNull('device_token')
            ->get();
        if ($devices->isEmpty()) {
            return;
        }

        $title = (string) ($notification['title'] ?? 'Sellova');
        $body = (string) ($notification['body'] ?? '');
        $data = [];
        foreach ($notification as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $data[(string) $key] = (string) ($value ?? '');
            }
        }

        foreach ($devices as $device) {
            try {
                $this->sendFcmMessage($settings, (string) $device->device_token, $title, $body, $data);
                $device->forceFill(['last_seen_at' => now()])->save();
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * @param array<string, string> $data
     */
    private function sendFcmMessage(
        PushNotificationSetting $settings,
        string $deviceToken,
        string $title,
        string $body,
        array $data,
    ): void {
        $projectId = trim((string) ($settings->fcm_project_id ?? ''));
        $clientEmail = trim((string) ($settings->fcm_client_email ?? ''));
        $privateKey = trim((string) ($settings->fcm_private_key ?? ''));
        if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
            return;
        }

        $accessToken = $this->mintAccessToken($clientEmail, $privateKey);
        if ($accessToken === null) {
            return;
        }

        Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => [
                        'notification' => [
                            'channel_id' => $settings->android_channel_id ?: 'sellova-default',
                        ],
                    ],
                ],
            ])
            ->throw();
    }

    private function mintAccessToken(string $clientEmail, string $privateKey): ?string
    {
        try {
            $jwt = JWT::encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => now()->timestamp,
                'exp' => now()->addHour()->timestamp,
            ], Str::replace('\\n', "\n", $privateKey), 'RS256');

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            if (! $response->successful()) {
                return null;
            }

            return (string) $response->json('access_token');
        } catch (Throwable) {
            return null;
        }
    }
}

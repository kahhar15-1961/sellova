<?php

declare(strict_types=1);

namespace App\Services\PushNotification;

use App\Models\PushNotificationSetting;

final class PushNotificationSettingsService
{
    public function current(): PushNotificationSetting
    {
        $settings = PushNotificationSetting::query()->first();
        if ($settings !== null) {
            return $settings;
        }

        return PushNotificationSetting::query()->create([
            'enabled' => false,
            'provider' => 'fcm',
            'android_channel_id' => 'sellova-default',
            'android_channel_name' => 'Sellova',
            'android_channel_description' => 'Order, wallet, and support alerts.',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data): PushNotificationSetting
    {
        $settings = $this->current();
        $settings->fill($data);
        $settings->save();

        return $settings->fresh() ?? $settings;
    }
}

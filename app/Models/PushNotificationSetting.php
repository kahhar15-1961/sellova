<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $enabled
 * @property string $provider
 * @property string|null $fcm_project_id
 * @property string|null $fcm_client_email
 * @property string|null $fcm_private_key
 * @property string $android_channel_id
 * @property string $android_channel_name
 * @property string $android_channel_description
 * @property Carbon|null $last_tested_at
 */
class PushNotificationSetting extends Model
{
    protected $table = 'push_notification_settings';

    protected $fillable = [
        'enabled',
        'provider',
        'fcm_project_id',
        'fcm_client_email',
        'fcm_private_key',
        'android_channel_id',
        'android_channel_name',
        'android_channel_description',
        'last_tested_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'provider' => 'string',
        'fcm_project_id' => 'string',
        'fcm_client_email' => 'string',
        'fcm_private_key' => 'encrypted',
        'android_channel_id' => 'string',
        'android_channel_name' => 'string',
        'android_channel_description' => 'string',
        'last_tested_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

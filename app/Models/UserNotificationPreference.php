<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'user_id',
        'in_app_enabled',
        'email_enabled',
        'order_updates_enabled',
        'promotion_enabled',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'order_updates_enabled' => 'boolean',
        'promotion_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


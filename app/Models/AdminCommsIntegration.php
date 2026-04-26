<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminCommsIntegration extends Model
{
    protected $table = 'admin_comms_integrations';

    protected $fillable = [
        'name',
        'channel',
        'webhook_url',
        'email_to',
        'is_enabled',
        'last_tested_at',
        'config_json',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_tested_at' => 'datetime',
        'config_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

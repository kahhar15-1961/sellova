<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileViewLog extends Model
{
    protected $table = 'profile_view_logs';

    protected $fillable = [
        'viewer_id',
        'viewer_role',
        'profile_type',
        'profile_id',
        'visibility_context',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'viewer_id' => 'integer',
        'profile_id' => 'integer',
    ];
}

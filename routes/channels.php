<?php

use App\Admin\AdminPermission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.approval.{approvalId}', static function (User $user, int $approvalId): array|bool {
    if (! $user->hasPermissionCode(AdminPermission::ACCESS)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->email ?? ('User #'.$user->id),
    ];
});

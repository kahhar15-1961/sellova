<?php

use App\Admin\AdminPermission;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.thread.{threadId}', static function (User $user, int $threadId): array|bool {
    $thread = ChatThread::query()->find($threadId);
    if (! $thread) {
        return false;
    }

    if ((int) $thread->buyer_user_id === (int) $user->id || (int) ($thread->seller_user_id ?? 0) === (int) $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->email ?? ('User #'.$user->id),
        ];
    }

    if ($user->hasPermissionCode(AdminPermission::ACCESS)) {
        return [
            'id' => $user->id,
            'name' => $user->email ?? ('User #'.$user->id),
        ];
    }

    return false;
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

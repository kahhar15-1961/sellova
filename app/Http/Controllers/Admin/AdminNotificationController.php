<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminNotificationController extends AdminPageController
{
    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            Notification::query()
                ->where('user_id', $user->id)
                ->where('template_code', 'like', 'admin.%')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notifications marked as read.');
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || (int) $notification->user_id !== (int) $user->id) {
            abort(403);
        }

        if (! str_starts_with((string) $notification->template_code, 'admin.')) {
            abort(403);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back();
    }
}

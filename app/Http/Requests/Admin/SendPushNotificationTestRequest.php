<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Http\Request;

final class SendPushNotificationTestRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Request $request): array
    {
        return $request->validate([
            'recipient_email' => ['nullable', 'string', 'email', 'max:255'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:500'],
        ]);
    }
}

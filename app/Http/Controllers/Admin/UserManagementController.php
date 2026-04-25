<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateUserAdminStateRequest;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;

final class UserManagementController
{
    public function updateState(UpdateUserAdminStateRequest $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $before = [
            'status' => $user->status,
            'risk_level' => $user->risk_level,
        ];

        $user->status = (string) $request->validated('status');
        if ($request->filled('risk_level')) {
            $user->risk_level = (string) $request->validated('risk_level');
        }
        $user->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.user.state_updated',
            targetType: 'user',
            targetId: $user->id,
            beforeJson: $before,
            afterJson: [
                'status' => $user->status,
                'risk_level' => $user->risk_level,
            ],
            reasonCode: $request->validated('reason'),
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User state updated successfully.');
    }
}

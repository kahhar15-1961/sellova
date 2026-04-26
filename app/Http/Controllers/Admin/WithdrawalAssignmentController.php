<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WithdrawalAssignmentController
{
    public function claim(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($withdrawal->assigned_to_user_id !== null && $withdrawal->assigned_to_user_id !== $actor->id) {
            return back()->with('error', 'Withdrawal is already assigned to another reviewer.');
        }

        $before = [
            'assigned_to_user_id' => $withdrawal->assigned_to_user_id,
            'assigned_at' => $withdrawal->assigned_at?->toIso8601String(),
        ];

        $withdrawal->assigned_to_user_id = $actor->id;
        $withdrawal->assigned_at = now();
        $withdrawal->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.withdrawal.claim',
            targetType: 'withdrawal_request',
            targetId: $withdrawal->id,
            beforeJson: $before,
            afterJson: [
                'assigned_to_user_id' => $withdrawal->assigned_to_user_id,
                'assigned_at' => $withdrawal->assigned_at?->toIso8601String(),
            ],
            reasonCode: 'assignment',
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('success', 'Withdrawal request assigned to you.');
    }

    public function unclaim(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($withdrawal->assigned_to_user_id !== $actor->id) {
            return back()->with('error', 'Only the assignee can release this withdrawal.');
        }

        $before = [
            'assigned_to_user_id' => $withdrawal->assigned_to_user_id,
            'assigned_at' => $withdrawal->assigned_at?->toIso8601String(),
        ];

        $withdrawal->assigned_to_user_id = null;
        $withdrawal->assigned_at = null;
        $withdrawal->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.withdrawal.unclaim',
            targetType: 'withdrawal_request',
            targetId: $withdrawal->id,
            beforeJson: $before,
            afterJson: [
                'assigned_to_user_id' => null,
                'assigned_at' => null,
            ],
            reasonCode: 'assignment_release',
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('success', 'Withdrawal request released to queue.');
    }
}

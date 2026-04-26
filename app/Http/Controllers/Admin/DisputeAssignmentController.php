<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\DisputeCase;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DisputeAssignmentController
{
    public function claim(Request $request, DisputeCase $dispute): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($dispute->assigned_to_user_id !== null && $dispute->assigned_to_user_id !== $actor->id) {
            return back()->with('error', 'Case is already assigned to another reviewer.');
        }

        $before = [
            'assigned_to_user_id' => $dispute->assigned_to_user_id,
            'assigned_at' => $dispute->assigned_at?->toIso8601String(),
        ];

        $dispute->assigned_to_user_id = $actor->id;
        $dispute->assigned_at = now();
        $dispute->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.dispute.claim',
            targetType: 'dispute_case',
            targetId: $dispute->id,
            beforeJson: $before,
            afterJson: [
                'assigned_to_user_id' => $dispute->assigned_to_user_id,
                'assigned_at' => $dispute->assigned_at?->toIso8601String(),
            ],
            reasonCode: 'assignment',
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('success', 'Dispute case assigned to you.');
    }

    public function unclaim(Request $request, DisputeCase $dispute): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($dispute->assigned_to_user_id !== $actor->id) {
            return back()->with('error', 'Only the assignee can release this case.');
        }

        $before = [
            'assigned_to_user_id' => $dispute->assigned_to_user_id,
            'assigned_at' => $dispute->assigned_at?->toIso8601String(),
        ];

        $dispute->assigned_to_user_id = null;
        $dispute->assigned_at = null;
        $dispute->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.dispute.unclaim',
            targetType: 'dispute_case',
            targetId: $dispute->id,
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

        return back()->with('success', 'Dispute case released to queue.');
    }
}

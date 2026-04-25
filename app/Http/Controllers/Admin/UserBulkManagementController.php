<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\BulkUserStateRequest;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;

final class UserBulkManagementController
{
    public function updateState(BulkUserStateRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $selectAll = (bool) ($validated['select_all'] ?? false);
        $ids = array_values(array_unique(array_map('intval', $validated['ids'] ?? [])));
        $status = (string) $request->validated('status');
        $risk = $request->validated('risk_level');
        $reason = $request->validated('reason');

        $updated = 0;
        $usersQuery = User::query();
        if ($selectAll) {
            $fq = trim((string) data_get($validated, 'filters.q', ''));
            $fStatus = trim((string) data_get($validated, 'filters.status', ''));
            if ($fStatus !== '') {
                $usersQuery->where('status', $fStatus);
            }
            if ($fq !== '') {
                $usersQuery->where(function ($w) use ($fq): void {
                    $w->where('email', 'like', '%'.$fq.'%')
                        ->orWhere('phone', 'like', '%'.$fq.'%');
                });
            }
        } else {
            $usersQuery->whereIn('id', $ids);
        }

        $users = $usersQuery->get();
        foreach ($users as $user) {
            $before = ['status' => $user->status, 'risk_level' => $user->risk_level];
            $user->status = $status;
            if ($risk !== null && $risk !== '') {
                $user->risk_level = (string) $risk;
            }
            $user->save();
            $updated++;

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.user.bulk_state_updated',
                targetType: 'user',
                targetId: $user->id,
                beforeJson: $before,
                afterJson: ['status' => $user->status, 'risk_level' => $user->risk_level],
                reasonCode: $reason,
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return redirect()->route('admin.users.index')->with('success', "Bulk user update applied to {$updated} account(s).");
    }
}

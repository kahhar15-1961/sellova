<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateAdminEscalationIncidentRequest;
use App\Models\AdminEscalationIncident;
use App\Models\User;
use App\Services\Admin\EscalationOperationsService;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class AdminEscalationIncidentActionController
{
    public function __invoke(
        UpdateAdminEscalationIncidentRequest $request,
        EscalationOperationsService $ops,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $incident = AdminEscalationIncident::query()->findOrFail((int) $request->input('incident_id'));
        $action = (string) $request->validated('action');

        $before = [
            'status' => $incident->status,
            'assigned_user_id' => $incident->assigned_user_id,
            'acknowledged_at' => $incident->acknowledged_at?->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
        ];

        if ($action === 'acknowledge') {
            $ops->acknowledge($incident, $actor->id);
        } elseif ($action === 'resolve') {
            $ops->resolve($incident, $actor->id, (string) ($request->validated('resolution_reason') ?? 'resolved'));
        } else {
            $assigneeId = (int) $request->validated('assignee_user_id');
            $ops->reassign($incident, $actor->id, $assigneeId);
        }

        $incident->refresh();
        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.escalation.incident.'.$action,
            targetType: 'admin_escalation_incident',
            targetId: $incident->id,
            beforeJson: $before,
            afterJson: [
                'status' => $incident->status,
                'assigned_user_id' => $incident->assigned_user_id,
                'acknowledged_at' => $incident->acknowledged_at?->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
            ],
            reasonCode: $action,
            correlationId: (string) Str::uuid(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('success', 'Escalation incident updated.');
    }
}

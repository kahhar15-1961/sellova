<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateAdminRunbookStepExecutionRequest;
use App\Models\AdminEscalationIncident;
use App\Models\User;
use App\Services\Admin\RunbookExecutionService;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class AdminEscalationRunbookExecutionActionController
{
    public function __invoke(UpdateAdminRunbookStepExecutionRequest $request, RunbookExecutionService $runbooks): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $incident = AdminEscalationIncident::query()->findOrFail((int) $request->validated('incident_id'));
        $execution = $runbooks->ensureExecution($incident, $actor->id);
        if ($execution === null) {
            return back()->with('error', 'No active runbook available for this incident.');
        }

        $stepExecutionId = (int) $request->validated('step_execution_id');
        $runbooks->completeStep(
            execution: $execution,
            stepExecutionId: $stepExecutionId,
            actorUserId: $actor->id,
            evidenceNotes: (string) ($request->validated('evidence_notes') ?? ''),
        );

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.escalation.runbook.step.completed',
            targetType: 'admin_escalation_incident',
            targetId: $incident->id,
            beforeJson: ['step_execution_id' => $stepExecutionId, 'status' => 'pending'],
            afterJson: ['step_execution_id' => $stepExecutionId, 'status' => 'completed'],
            reasonCode: 'runbook_step_completion',
            correlationId: (string) Str::uuid(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('success', 'Runbook step completed.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminEscalationIncident;
use App\Models\AdminRunbook;
use App\Models\AdminRunbookExecution;
use App\Models\AdminRunbookStepExecution;

final class RunbookExecutionService
{
    public function ensureExecution(AdminEscalationIncident $incident, ?int $startedByUserId = null): ?AdminRunbookExecution
    {
        $runbook = AdminRunbook::query()
            ->where('queue_code', $incident->queue_code)
            ->where('is_active', true)
            ->with('steps')
            ->orderBy('id')
            ->first();

        if ($runbook === null) {
            return null;
        }

        $execution = AdminRunbookExecution::query()->firstOrCreate(
            [
                'incident_id' => $incident->id,
                'runbook_id' => $runbook->id,
            ],
            [
                'started_by_user_id' => $startedByUserId,
                'status' => 'in_progress',
                'started_at' => now(),
            ],
        );

        foreach ($runbook->steps as $step) {
            AdminRunbookStepExecution::query()->firstOrCreate(
                [
                    'execution_id' => $execution->id,
                    'runbook_step_id' => $step->id,
                ],
                [
                    'status' => 'pending',
                ],
            );
        }

        return $execution->fresh(['runbook.steps', 'steps.runbook_step']);
    }

    public function completeStep(AdminRunbookExecution $execution, int $stepExecutionId, int $actorUserId, ?string $evidenceNotes): void
    {
        $stepExecution = AdminRunbookStepExecution::query()
            ->where('execution_id', $execution->id)
            ->whereKey($stepExecutionId)
            ->firstOrFail();

        $stepExecution->forceFill([
            'status' => 'completed',
            'completed_by_user_id' => $actorUserId,
            'evidence_notes' => $evidenceNotes,
            'completed_at' => now(),
        ])->save();

        $execution->refresh();
        $pendingRequired = AdminRunbookStepExecution::query()
            ->where('execution_id', $execution->id)
            ->where('status', '!=', 'completed')
            ->whereHas('runbook_step', static fn ($q) => $q->where('is_required', true))
            ->count();

        if ($pendingRequired === 0) {
            $execution->forceFill([
                'status' => 'completed',
                'completed_by_user_id' => $actorUserId,
                'completed_at' => now(),
            ])->save();
        }
    }

    public function canResolve(AdminEscalationIncident $incident): bool
    {
        $execution = AdminRunbookExecution::query()
            ->where('incident_id', $incident->id)
            ->latest('id')
            ->first();

        if ($execution === null) {
            return true;
        }

        $pendingRequired = AdminRunbookStepExecution::query()
            ->where('execution_id', $execution->id)
            ->where('status', '!=', 'completed')
            ->whereHas('runbook_step', static fn ($q) => $q->where('is_required', true))
            ->count();

        return $pendingRequired === 0;
    }
}

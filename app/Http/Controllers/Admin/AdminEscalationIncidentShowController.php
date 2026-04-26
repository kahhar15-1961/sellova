<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AdminEscalationIncident;
use App\Services\Admin\RunbookExecutionService;
use Inertia\Inertia;
use Inertia\Response;

final class AdminEscalationIncidentShowController extends AdminPageController
{
    public function __invoke(AdminEscalationIncident $incident, RunbookExecutionService $runbooks): Response
    {
        $incident->load([
            'assigned_user:id,email',
            'events.actor_user:id,email',
            'commsDeliveryLogs.integration:id,name,channel',
            'runbookExecutions.runbook:id,title,queue_code',
            'runbookExecutions.steps.runbook_step:id,step_order,instruction,is_required,evidence_required',
            'runbookExecutions.steps',
        ]);

        $execution = $runbooks->ensureExecution($incident);
        $incident->load([
            'runbookExecutions.runbook:id,title,queue_code',
            'runbookExecutions.steps.runbook_step:id,step_order,instruction,is_required,evidence_required',
            'runbookExecutions.steps',
        ]);
        $activeExecution = $execution ?? $incident->runbookExecutions->sortByDesc('id')->first();

        return Inertia::render('Admin/Escalations/Show', [
            'header' => $this->pageHeader(
                'Escalation Incident #'.$incident->id,
                'Deep workspace for incident timeline, runbook execution, comms delivery, and accountable resolution.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Escalations', 'href' => route('admin.escalations.index')],
                    ['label' => 'Incident #'.$incident->id],
                ],
            ),
            'incident' => [
                'id' => $incident->id,
                'queue_code' => $incident->queue_code,
                'target_type' => $incident->target_type,
                'target_id' => $incident->target_id,
                'status' => $incident->status,
                'severity' => $incident->severity,
                'reason_code' => $incident->reason_code,
                'assigned_user_id' => $incident->assigned_user_id,
                'assigned_user' => $incident->assigned_user?->email,
                'opened_at' => $incident->opened_at?->toIso8601String(),
                'ack_due_at' => $incident->ack_due_at?->toIso8601String(),
                'resolve_due_at' => $incident->resolve_due_at?->toIso8601String(),
                'acknowledged_at' => $incident->acknowledged_at?->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'current_ladder_level' => $incident->current_ladder_level,
            ],
            'runbook_execution' => $activeExecution ? [
                'id' => $activeExecution->id,
                'runbook_title' => $activeExecution->runbook?->title,
                'status' => $activeExecution->status,
                'step_action_url' => route('admin.escalations.runbook-step.action'),
                'steps' => $activeExecution->steps
                    ->sortBy(static fn ($s) => (int) ($s->runbook_step?->step_order ?? 0))
                    ->values()
                    ->map(static fn ($s): array => [
                        'id' => $s->id,
                        'status' => $s->status,
                        'evidence_notes' => $s->evidence_notes,
                        'completed_at' => $s->completed_at?->toIso8601String(),
                        'step_order' => $s->runbook_step?->step_order,
                        'instruction' => $s->runbook_step?->instruction,
                        'is_required' => (bool) ($s->runbook_step?->is_required ?? false),
                        'evidence_required' => (bool) ($s->runbook_step?->evidence_required ?? false),
                    ])->all(),
            ] : null,
            'timeline' => $incident->events
                ->sortByDesc('id')
                ->values()
                ->map(static fn ($e): array => [
                    'id' => $e->id,
                    'event_type' => $e->event_type,
                    'actor' => $e->actor_user?->email ?? 'System',
                    'payload' => $e->payload_json ?? [],
                    'created_at' => $e->created_at?->toIso8601String(),
                ])->all(),
            'comms_logs' => $incident->commsDeliveryLogs
                ->sortByDesc('id')
                ->values()
                ->map(static fn ($l): array => [
                    'id' => $l->id,
                    'event_type' => $l->event_type,
                    'status' => $l->status,
                    'attempt_count' => $l->attempt_count,
                    'integration' => $l->integration?->name ? ($l->integration->name.' ('.$l->integration->channel.')') : '—',
                    'last_error' => $l->last_error,
                    'next_retry_at' => $l->next_retry_at?->toIso8601String(),
                    'delivered_at' => $l->delivered_at?->toIso8601String(),
                ])->all(),
        ]);
    }
}

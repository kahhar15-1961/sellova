<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AdminCommsDeliveryLog;
use App\Models\AdminEscalationEvent;
use App\Models\AdminEscalationIncident;
use App\Models\AdminRunbookStepExecution;
use App\Models\DisputeCase;
use App\Models\WithdrawalRequest;
use App\Services\Admin\CommsDeliveryService;
use App\Services\Admin\RunbookExecutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AdminEscalationIncidentDetailController extends AdminPageController
{
    public function show(AdminEscalationIncident $incident): Response
    {
        $incident->load([
            'assigned_user:id,email',
            'events.actor_user:id,email',
            'runbookExecutions.runbook:id,title,queue_code',
            'runbookExecutions.steps.runbook_step:id,step_order,instruction,is_required,evidence_required',
            'runbookExecutions.steps.execution:id,status',
            'runbookExecutions.steps.completed_by_user:id,email',
            'runbookExecutions.started_by_user:id,email',
            'runbookExecutions.completed_by_user:id,email',
            'commsDeliveryLogs.integration:id,name,channel',
        ]);

        $latestExecution = $incident->runbookExecutions->sortByDesc('id')->first();
        $stepRows = $latestExecution?->steps
            ? $latestExecution->steps->sortBy(static fn ($s) => (int) ($s->runbook_step?->step_order ?? 0))->values()
            : collect();

        $requiredTotal = (int) $stepRows->filter(static fn ($s): bool => (bool) ($s->runbook_step?->is_required))->count();
        $requiredCompleted = (int) $stepRows->filter(static fn ($s): bool => (bool) ($s->runbook_step?->is_required) && $s->status === 'completed')->count();

        return Inertia::render('Admin/Escalations/Show', [
            'header' => $this->pageHeader(
                'Escalation Incident #'.$incident->id,
                'Incident workspace with timeline, runbook execution, and comms delivery observability.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Escalations', 'href' => route('admin.escalations.index')],
                    ['label' => '#'.$incident->id],
                ],
            ),
            'incident' => [
                'id' => $incident->id,
                'queue_code' => $incident->queue_code,
                'target_type' => $incident->target_type,
                'target_id' => $incident->target_id,
                'target_href' => $this->targetHref($incident),
                'status' => $incident->status,
                'severity' => $incident->severity,
                'reason_code' => $incident->reason_code,
                'assigned_user' => $incident->assigned_user?->email ?? 'Unassigned',
                'opened_at' => $incident->opened_at?->toIso8601String(),
                'acknowledged_at' => $incident->acknowledged_at?->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'ack_due_at' => $incident->ack_due_at?->toIso8601String(),
                'resolve_due_at' => $incident->resolve_due_at?->toIso8601String(),
                'current_ladder_level' => (int) $incident->current_ladder_level,
                'next_ladder_at' => $incident->next_ladder_at?->toIso8601String(),
                'meta_json' => $incident->meta_json ?? [],
                'target_snapshot' => $this->targetSnapshot($incident),
            ],
            'runbook' => $latestExecution ? [
                'execution_id' => $latestExecution->id,
                'title' => $latestExecution->runbook?->title ?? 'Runbook',
                'status' => $latestExecution->status,
                'required_total' => $requiredTotal,
                'required_completed' => $requiredCompleted,
                'steps' => $stepRows->map(static fn ($s): array => [
                    'id' => $s->id,
                    'step_order' => $s->runbook_step?->step_order ?? 0,
                    'instruction' => $s->runbook_step?->instruction ?? '—',
                    'is_required' => (bool) ($s->runbook_step?->is_required),
                    'evidence_required' => (bool) ($s->runbook_step?->evidence_required),
                    'status' => $s->status,
                    'evidence_notes' => $s->evidence_notes,
                    'completed_by' => $s->completed_by_user?->email ?? '—',
                    'completed_at' => $s->completed_at?->toIso8601String(),
                ])->all(),
            ] : null,
            'events' => $incident->events
                ->sortByDesc('created_at')
                ->values()
                ->map(static fn ($e): array => [
                    'id' => $e->id,
                    'event_type' => $e->event_type,
                    'actor' => $e->actor_user?->email ?? 'system',
                    'created_at' => $e->created_at?->toIso8601String(),
                    'payload_json' => $e->payload_json ?? [],
                ])->all(),
            'comms_logs' => $incident->commsDeliveryLogs
                ->sortByDesc('id')
                ->values()
                ->map(static fn ($l): array => [
                    'id' => $l->id,
                    'integration' => $l->integration?->name ?? '—',
                    'channel' => $l->integration?->channel ?? '—',
                    'event_type' => $l->event_type,
                    'status' => $l->status,
                    'attempt_count' => (int) $l->attempt_count,
                    'last_error' => $l->last_error,
                    'next_retry_at' => $l->next_retry_at?->toIso8601String(),
                    'delivered_at' => $l->delivered_at?->toIso8601String(),
                ])->all(),
            'complete_step_url_template' => route('admin.escalations.steps.complete', ['incident' => $incident, 'stepExecution' => '__STEP__']),
            'retry_comms_url_template' => route('admin.escalations.comms.retry', ['incident' => $incident, 'deliveryLog' => '__LOG__']),
        ]);
    }

    public function completeRunbookStep(
        Request $request,
        AdminEscalationIncident $incident,
        AdminRunbookStepExecution $stepExecution,
        RunbookExecutionService $runbooks,
    ): RedirectResponse {
        $data = $request->validate([
            'evidence_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $stepExecution->load('runbook_step');
        if ((bool) ($stepExecution->runbook_step?->evidence_required) && trim((string) ($data['evidence_notes'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'evidence_notes' => 'Evidence notes are required for this runbook step.',
            ]);
        }

        $execution = $stepExecution->execution;
        if ($execution === null || (int) $execution->incident_id !== (int) $incident->id) {
            return back()->with('error', 'Runbook step does not belong to this incident.');
        }

        $actorId = (int) $request->user()->id;
        $runbooks->completeStep($execution, (int) $stepExecution->id, $actorId, (string) ($data['evidence_notes'] ?? ''));
        AdminEscalationEvent::query()->create([
            'incident_id' => $incident->id,
            'actor_user_id' => $actorId,
            'event_type' => 'incident.runbook.step.completed',
            'payload_json' => [
                'step_execution_id' => $stepExecution->id,
                'runbook_step_id' => $stepExecution->runbook_step_id,
                'evidence_notes' => (string) ($data['evidence_notes'] ?? ''),
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Runbook step marked complete.');
    }

    public function retryCommsDelivery(
        AdminEscalationIncident $incident,
        AdminCommsDeliveryLog $deliveryLog,
        CommsDeliveryService $comms,
        Request $request,
    ): RedirectResponse {
        if ((int) $deliveryLog->incident_id !== (int) $incident->id) {
            return back()->with('error', 'Delivery log does not belong to this incident.');
        }

        $comms->attemptDelivery($deliveryLog->fresh(['integration']));
        AdminEscalationEvent::query()->create([
            'incident_id' => $incident->id,
            'actor_user_id' => (int) $request->user()->id,
            'event_type' => 'incident.comms.retry.triggered',
            'payload_json' => [
                'delivery_log_id' => $deliveryLog->id,
                'integration_id' => $deliveryLog->integration_id,
                'status_after' => $deliveryLog->fresh()->status,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Comms delivery retry attempted.');
    }

    private function targetHref(AdminEscalationIncident $incident): ?string
    {
        return match ($incident->target_type) {
            'dispute_case' => route('admin.disputes.show', ['dispute' => $incident->target_id]),
            'withdrawal_request' => route('admin.withdrawals.show', ['withdrawal' => $incident->target_id]),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function targetSnapshot(AdminEscalationIncident $incident): ?array
    {
        if ($incident->target_type === 'dispute_case') {
            $case = DisputeCase::query()
                ->with(['order:id,order_number', 'assigned_to_user:id,email', 'opened_by_user:id,email'])
                ->find($incident->target_id);
            if ($case === null) {
                return null;
            }

            return [
                'type' => 'dispute_case',
                'id' => $case->id,
                'status' => $case->status->value,
                'order_number' => $case->order?->order_number,
                'opened_by' => $case->opened_by_user?->email,
                'assignee' => $case->assigned_to_user?->email,
                'opened_at' => $case->opened_at?->toIso8601String() ?? $case->created_at?->toIso8601String(),
                'escalated_at' => $case->escalated_at?->toIso8601String(),
                'escalation_reason' => $case->escalation_reason,
            ];
        }

        if ($incident->target_type === 'withdrawal_request') {
            $wr = WithdrawalRequest::query()
                ->with(['seller_profile:id,display_name', 'assigned_to_user:id,email'])
                ->find($incident->target_id);
            if ($wr === null) {
                return null;
            }

            return [
                'type' => 'withdrawal_request',
                'id' => $wr->id,
                'status' => $wr->status->value,
                'seller' => $wr->seller_profile?->display_name,
                'assignee' => $wr->assigned_to_user?->email,
                'amount' => trim(($wr->currency ?? '').' '.(string) $wr->requested_amount),
                'requested_at' => $wr->created_at?->toIso8601String(),
                'escalated_at' => $wr->escalated_at?->toIso8601String(),
                'escalation_reason' => $wr->escalation_reason,
            ];
        }

        return null;
    }
}

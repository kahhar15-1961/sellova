<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminCommsIntegration;
use App\Models\AdminEscalationEvent;
use App\Models\AdminEscalationIncident;
use App\Models\AdminEscalationPolicy;
use App\Models\AdminOnCallRotation;
use App\Models\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class EscalationOperationsService
{
    public function __construct(
        private readonly CommsDeliveryService $commsDelivery,
        private readonly RunbookExecutionService $runbookExecution,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function openFromBreach(
        string $queueCode,
        string $targetType,
        int $targetId,
        string $reasonCode,
        \DateTimeInterface $breachedAt,
        array $meta = [],
    ): AdminEscalationIncident {
        $policy = $this->policyForQueue($queueCode);
        $incident = AdminEscalationIncident::query()->firstOrNew([
            'queue_code' => $queueCode,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);

        $isNew = ! $incident->exists;
        $now = now();
        $ackDueAt = $now->copy()->addMinutes((int) ($policy?->ack_sla_minutes ?? 30));
        $resolveDueAt = $now->copy()->addMinutes((int) ($policy?->resolve_sla_minutes ?? 240));
        $nextLadderAt = $this->nextLadderAt($policy, $now, 1);

        $incident->forceFill([
            'uuid' => $incident->uuid ?: (string) Str::uuid(),
            'status' => 'open',
            'severity' => (string) ($policy?->default_severity ?: 'high'),
            'reason_code' => $reasonCode,
            'sla_breached_at' => $breachedAt,
            'opened_at' => $incident->opened_at ?? $now,
            'ack_due_at' => $incident->ack_due_at ?? $ackDueAt,
            'resolve_due_at' => $incident->resolve_due_at ?? $resolveDueAt,
            'next_ladder_at' => $nextLadderAt,
            'last_ladder_triggered_at' => null,
            'current_ladder_level' => max(1, (int) $incident->current_ladder_level),
            'resolved_at' => null,
            'meta_json' => $meta,
        ]);

        if ($policy?->auto_assign_on_call) {
            $assigneeId = $this->resolveOnCallAssigneeId($policy->on_call_role_code);
            if ($assigneeId !== null) {
                $incident->assigned_user_id = $assigneeId;
            }
        }

        $incident->save();
        $this->runbookExecution->ensureExecution($incident);

        $this->event($incident, null, $isNew ? 'incident.opened' : 'incident.reopened', [
            'reason_code' => $reasonCode,
            'severity' => $incident->severity,
            'assigned_user_id' => $incident->assigned_user_id,
        ]);

        $this->notifyInApp($incident);
        $this->notifyComms($incident, $policy?->comms_integration_id);

        return $incident;
    }

    public function acknowledge(AdminEscalationIncident $incident, int $actorUserId): void
    {
        $incident->forceFill([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'next_ladder_at' => null,
        ])->save();

        $this->event($incident, $actorUserId, 'incident.acknowledged', []);
    }

    public function resolve(AdminEscalationIncident $incident, int $actorUserId, string $reason): void
    {
        $incident->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'next_ladder_at' => null,
        ])->save();

        $this->event($incident, $actorUserId, 'incident.resolved', ['reason' => $reason]);
    }

    public function reassign(AdminEscalationIncident $incident, int $actorUserId, int $assigneeId): void
    {
        $incident->forceFill([
            'assigned_user_id' => $assigneeId,
        ])->save();

        $this->event($incident, $actorUserId, 'incident.reassigned', ['assignee_user_id' => $assigneeId]);
        $this->notifyInApp($incident);
    }

    private function policyForQueue(string $queueCode): ?AdminEscalationPolicy
    {
        return AdminEscalationPolicy::query()
            ->where('queue_code', $queueCode)
            ->where('is_enabled', true)
            ->first();
    }

    private function resolveOnCallAssigneeId(?string $roleCode): ?int
    {
        if ($roleCode === null || $roleCode === '') {
            return null;
        }

        $weekday = (int) now()->dayOfWeek;
        $hour = (int) now()->hour;

        $rotation = AdminOnCallRotation::query()
            ->where('role_code', $roleCode)
            ->where('is_active', true)
            ->where('weekday', $weekday)
            ->where('start_hour', '<=', $hour)
            ->where('end_hour', '>=', $hour)
            ->orderBy('priority')
            ->first();

        return $rotation?->user_id;
    }

    public function advanceLadder(AdminEscalationIncident $incident): bool
    {
        if ($incident->status !== 'open') {
            return false;
        }

        $policy = $this->policyForQueue($incident->queue_code);
        if ($policy === null) {
            return false;
        }

        $ladder = $this->normalizedLadder($policy);
        if ($ladder === []) {
            return false;
        }

        $currentLevel = max(1, (int) $incident->current_ladder_level);
        $nextLevel = $currentLevel + 1;
        if ($nextLevel > count($ladder)) {
            return false;
        }

        $stage = $ladder[$nextLevel - 1];
        if (! empty($stage['severity'])) {
            $incident->severity = (string) $stage['severity'];
        }
        if (! empty($stage['role_code'])) {
            $assigneeId = $this->resolveOnCallAssigneeId((string) $stage['role_code']);
            if ($assigneeId !== null) {
                $incident->assigned_user_id = $assigneeId;
            }
        }
        $incident->current_ladder_level = $nextLevel;
        $incident->last_ladder_triggered_at = now();
        $incident->next_ladder_at = $this->nextLadderAt($policy, now(), $nextLevel);
        $incident->save();

        $this->event($incident, null, 'incident.ladder.advanced', [
            'new_level' => $nextLevel,
            'severity' => $incident->severity,
            'assigned_user_id' => $incident->assigned_user_id,
        ]);
        $this->notifyInApp($incident);
        $this->notifyComms($incident, $policy->comms_integration_id, 'admin.escalation.incident.ladder_advanced');

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedLadder(AdminEscalationPolicy $policy): array
    {
        $raw = $policy->escalation_ladder_json;
        if (! is_array($raw) || $raw === []) {
            return [
                ['after_minutes' => (int) max(5, min(1440, (int) floor($policy->ack_sla_minutes / 2))), 'severity' => 'critical', 'role_code' => $policy->on_call_role_code],
                ['after_minutes' => (int) max(10, (int) $policy->ack_sla_minutes), 'severity' => 'critical', 'role_code' => $policy->on_call_role_code],
            ];
        }

        return array_values(array_filter(array_map(static function ($stage): ?array {
            if (! is_array($stage)) {
                return null;
            }
            $after = max(1, (int) ($stage['after_minutes'] ?? 0));

            return [
                'after_minutes' => $after,
                'severity' => (string) ($stage['severity'] ?? 'critical'),
                'role_code' => isset($stage['role_code']) ? (string) $stage['role_code'] : null,
            ];
        }, $raw)));
    }

    private function nextLadderAt(?AdminEscalationPolicy $policy, \DateTimeInterface $anchor, int $level): ?\DateTimeInterface
    {
        if ($policy === null) {
            return null;
        }

        $ladder = $this->normalizedLadder($policy);
        $index = $level - 1;
        if (! isset($ladder[$index])) {
            return null;
        }

        return now()->setTimestamp($anchor->getTimestamp())->addMinutes((int) Arr::get($ladder[$index], 'after_minutes', 15));
    }

    private function notifyInApp(AdminEscalationIncident $incident): void
    {
        if ($incident->assigned_user_id === null) {
            return;
        }

        Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $incident->assigned_user_id,
            'channel' => 'in_app',
            'template_code' => 'admin.escalation.incident',
            'payload_json' => [
                'incident_id' => $incident->id,
                'queue_code' => $incident->queue_code,
                'target_type' => $incident->target_type,
                'target_id' => $incident->target_id,
                'severity' => $incident->severity,
                'status' => $incident->status,
                'href' => $this->incidentHref($incident),
            ],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $incident->forceFill(['last_notified_at' => now()])->save();
    }

    private function incidentHref(AdminEscalationIncident $incident): ?string
    {
        return match ($incident->queue_code) {
            'seller_kyc' => route('admin.sellers.kyc.show', ['kyc' => $incident->target_id]),
            'disputes' => route('admin.disputes.show', ['dispute' => $incident->target_id]),
            'withdrawals' => route('admin.withdrawals.show', ['withdrawal' => $incident->target_id]),
            default => route('admin.escalations.show', ['incident' => $incident->id]),
        };
    }

    private function notifyComms(AdminEscalationIncident $incident, ?int $integrationId, string $eventType = 'admin.escalation.incident.opened'): void
    {
        if ($integrationId === null) {
            return;
        }

        $integration = AdminCommsIntegration::query()
            ->whereKey($integrationId)
            ->where('is_enabled', true)
            ->first();
        if ($integration === null) {
            return;
        }

        $this->commsDelivery->queueDelivery(
            incident: $incident,
            integration: $integration,
            eventType: $eventType,
            payload: [
                'event' => $eventType,
                'message' => sprintf(
                    'Escalation incident #%d (%s %s #%d) status=%s severity=%s',
                    $incident->id,
                    $incident->queue_code,
                    $incident->target_type,
                    $incident->target_id,
                    $incident->status,
                    $incident->severity,
                ),
                'incident' => [
                    'id' => $incident->id,
                    'queue_code' => $incident->queue_code,
                    'target_type' => $incident->target_type,
                    'target_id' => $incident->target_id,
                    'severity' => $incident->severity,
                    'status' => $incident->status,
                    'current_ladder_level' => $incident->current_ladder_level,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(AdminEscalationIncident $incident, ?int $actorUserId, string $eventType, array $payload): void
    {
        AdminEscalationEvent::query()->create([
            'incident_id' => $incident->id,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'payload_json' => $payload,
            'created_at' => now(),
        ]);
    }
}

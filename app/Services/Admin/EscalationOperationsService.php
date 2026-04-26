<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminCommsIntegration;
use App\Models\AdminEscalationEvent;
use App\Models\AdminEscalationIncident;
use App\Models\AdminEscalationPolicy;
use App\Models\AdminOnCallRotation;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class EscalationOperationsService
{
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
        $incident->forceFill([
            'uuid' => $incident->uuid ?: (string) Str::uuid(),
            'status' => 'open',
            'severity' => (string) ($policy?->default_severity ?: 'high'),
            'reason_code' => $reasonCode,
            'sla_breached_at' => $breachedAt,
            'opened_at' => $incident->opened_at ?? now(),
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
        ])->save();

        $this->event($incident, $actorUserId, 'incident.acknowledged', []);
    }

    public function resolve(AdminEscalationIncident $incident, int $actorUserId, string $reason): void
    {
        $incident->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
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
            ],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $incident->forceFill(['last_notified_at' => now()])->save();
    }

    private function notifyComms(AdminEscalationIncident $incident, ?int $integrationId): void
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

        try {
            if ($integration->channel === 'webhook' && $integration->webhook_url) {
                Http::timeout(5)->post($integration->webhook_url, [
                    'event' => 'admin.escalation.incident.opened',
                    'incident' => [
                        'id' => $incident->id,
                        'queue_code' => $incident->queue_code,
                        'target_type' => $incident->target_type,
                        'target_id' => $incident->target_id,
                        'severity' => $incident->severity,
                        'status' => $incident->status,
                    ],
                ])->throw();
            } elseif ($integration->channel === 'email' && $integration->email_to) {
                Mail::raw(
                    sprintf(
                        'Escalation incident #%d (%s %s #%d) opened with severity %s.',
                        $incident->id,
                        $incident->queue_code,
                        $incident->target_type,
                        $incident->target_id,
                        $incident->severity,
                    ),
                    static function ($msg) use ($integration): void {
                        $msg->to((string) $integration->email_to)
                            ->subject('Sellova escalation incident opened');
                    },
                );
            } else {
                return;
            }

            $integration->forceFill(['last_tested_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning('Escalation comms delivery failed.', [
                'integration_id' => $integration->id,
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
            ]);
        }
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

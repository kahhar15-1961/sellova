<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Admin\AdminPermission;
use App\Auth\RoleCodes;
use App\Domain\Enums\DisputeCaseStatus;
use App\Models\KycVerification;
use App\Domain\Enums\WithdrawalRequestStatus;
use App\Models\DisputeCase;
use App\Models\Notification;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SlaEscalationService
{
    public function __construct(
        private readonly EscalationOperationsService $escalationOps,
    ) {}

    /**
     * @return array{kyc_warnings: int, kyc_escalated: int, disputes_escalated: int, withdrawals_escalated: int}
     */
    public function run(): array
    {
        $kycWarningThreshold = (int) config('admin_sla.kyc.warning_hours', 12);
        $kycBreachThreshold = (int) config('admin_sla.kyc.breach_hours', 24);
        $disputeThreshold = (int) config('admin_sla.disputes.breach_hours', 48);
        $withdrawalThreshold = (int) config('admin_sla.withdrawals.breach_hours', 24);

        $kycWarnings = $this->warnKycCases($kycWarningThreshold);
        $kycEscalated = $this->escalateKycCases($kycBreachThreshold);
        $disputesEscalated = $this->escalateDisputes($disputeThreshold);
        $withdrawalsEscalated = $this->escalateWithdrawals($withdrawalThreshold);

        return [
            'kyc_warnings' => $kycWarnings,
            'kyc_escalated' => $kycEscalated,
            'disputes_escalated' => $disputesEscalated,
            'withdrawals_escalated' => $withdrawalsEscalated,
        ];
    }

    private function warnKycCases(int $warningHours): int
    {
        $cutoff = now()->subHours(max(1, $warningHours));
        $targets = KycVerification::query()
            ->whereIn('status', ['submitted', 'under_review'])
            ->whereNull('sla_warning_sent_at')
            ->where(function ($q) use ($cutoff): void {
                $q->where('submitted_at', '<=', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('submitted_at')->where('created_at', '<=', $cutoff);
                    });
            })
            ->limit(500)
            ->get();

        if ($targets->isEmpty()) {
            return 0;
        }

        $recipientIds = $this->staffUserIdsByPermission(AdminPermission::SELLERS_VERIFY);
        $now = now();

        foreach ($targets as $kyc) {
            $before = [
                'sla_warning_sent_at' => null,
                'submitted_at' => $kyc->submitted_at?->toIso8601String(),
                'assigned_to_user_id' => $kyc->assigned_to_user_id,
            ];

            $kyc->forceFill([
                'sla_warning_sent_at' => $now,
            ])->save();

            $auditorId = $this->resolveAuditActorId($kyc->assigned_to_user_id, $recipientIds);

            AuditLogWriter::write(
                actorUserId: $auditorId,
                action: 'admin.seller_kyc.sla_warning',
                targetType: 'kyc_verification',
                targetId: (int) $kyc->id,
                beforeJson: $before,
                afterJson: [
                    'sla_warning_sent_at' => $now->toIso8601String(),
                    'submitted_at' => $kyc->submitted_at?->toIso8601String(),
                    'assigned_to_user_id' => $kyc->assigned_to_user_id,
                ],
                reasonCode: 'sla_warning',
                correlationId: (string) Str::uuid(),
                ipAddress: null,
                userAgent: 'scheduler:sla-escalations',
            );

            $this->notifyRecipients(
                queueCode: 'seller_kyc',
                targetId: (int) $kyc->id,
                recipientIds: $this->appendAssignedUser($recipientIds, $kyc->assigned_to_user_id),
                templateCode: 'admin.sla.warning',
                reason: 'sla_warning',
            );
        }

        return $targets->count();
    }

    private function escalateKycCases(int $breachHours): int
    {
        $cutoff = now()->subHours(max(1, $breachHours));
        $targets = KycVerification::query()
            ->whereIn('status', ['submitted', 'under_review'])
            ->whereNull('escalated_at')
            ->where(function ($q) use ($cutoff): void {
                $q->where('submitted_at', '<=', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('submitted_at')->where('created_at', '<=', $cutoff);
                    });
            })
            ->limit(500)
            ->get();

        if ($targets->isEmpty()) {
            return 0;
        }

        $recipientIds = $this->staffUserIdsByPermission(AdminPermission::SELLERS_VERIFY);
        $now = now();

        foreach ($targets as $kyc) {
            $incident = $this->escalationOps->openFromBreach(
                queueCode: 'seller_kyc',
                targetType: 'kyc_verification',
                targetId: (int) $kyc->id,
                reasonCode: 'sla_breach',
                breachedAt: $now,
                meta: [
                    'source' => 'scheduler',
                    'seller_profile_id' => $kyc->seller_profile_id,
                    'current_assignee_user_id' => $kyc->assigned_to_user_id,
                ],
            );

            $before = [
                'status' => (string) $kyc->status,
                'assigned_to_user_id' => $kyc->assigned_to_user_id,
                'assigned_at' => $kyc->assigned_at?->toIso8601String(),
                'escalated_at' => $kyc->escalated_at?->toIso8601String(),
                'escalation_reason' => $kyc->escalation_reason,
            ];

            $updatedAssignee = $kyc->assigned_to_user_id;
            if ($incident->assigned_user_id !== null) {
                $updatedAssignee = (int) $incident->assigned_user_id;
                $kyc->assigned_to_user_id = $updatedAssignee;
                $kyc->assigned_at = $kyc->assigned_at ?? $now;
            }

            $kyc->forceFill([
                'status' => 'under_review',
                'escalated_at' => $now,
                'escalation_reason' => 'sla_breach',
            ])->save();

            $auditorId = $this->resolveAuditActorId($updatedAssignee, $recipientIds);

            AuditLogWriter::write(
                actorUserId: $auditorId,
                action: 'admin.seller_kyc.escalated',
                targetType: 'kyc_verification',
                targetId: (int) $kyc->id,
                beforeJson: $before,
                afterJson: [
                    'status' => 'under_review',
                    'assigned_to_user_id' => $kyc->assigned_to_user_id,
                    'assigned_at' => $kyc->assigned_at?->toIso8601String(),
                    'escalated_at' => $now->toIso8601String(),
                    'escalation_reason' => 'sla_breach',
                ],
                reasonCode: 'sla_breach',
                correlationId: (string) Str::uuid(),
                ipAddress: null,
                userAgent: 'scheduler:sla-escalations',
            );

            $this->notifyRecipients(
                queueCode: 'seller_kyc',
                targetId: (int) $kyc->id,
                recipientIds: $this->appendAssignedUser($recipientIds, $kyc->assigned_to_user_id),
                templateCode: 'admin.sla.escalated',
                reason: 'sla_breach',
            );
        }

        return $targets->count();
    }

    private function escalateDisputes(int $breachHours): int
    {
        $cutoff = now()->subHours(max(1, $breachHours));
        $targets = DisputeCase::query()
            ->where('status', '!=', DisputeCaseStatus::Resolved->value)
            ->whereNull('escalated_at')
            ->where(function ($q) use ($cutoff): void {
                $q->where('opened_at', '<=', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('opened_at')->where('created_at', '<=', $cutoff);
                    });
            })
            ->limit(500)
            ->get();

        if ($targets->isEmpty()) {
            return 0;
        }

        $recipientIds = $this->staffUserIdsByPermission(AdminPermission::DISPUTES_VIEW);
        $now = now();

        foreach ($targets as $case) {
            $case->forceFill([
                'escalated_at' => $now,
                'escalation_reason' => 'sla_breach',
            ])->save();

            $auditorId = $this->resolveAuditActorId($case->assigned_to_user_id, $recipientIds);

            AuditLogWriter::write(
                actorUserId: $auditorId,
                action: 'admin.dispute.escalated',
                targetType: 'dispute_case',
                targetId: (int) $case->id,
                beforeJson: [
                    'status' => (string) $case->status->value,
                    'escalated_at' => null,
                ],
                afterJson: [
                    'status' => (string) $case->status->value,
                    'escalated_at' => $now->toIso8601String(),
                    'escalation_reason' => 'sla_breach',
                ],
                reasonCode: 'sla_breach',
                correlationId: (string) Str::uuid(),
                ipAddress: null,
                userAgent: 'scheduler:sla-escalations',
            );

            $this->notifyRecipients(
                queueCode: 'disputes',
                targetId: (int) $case->id,
                recipientIds: $this->appendAssignedUser($recipientIds, $case->assigned_to_user_id),
                templateCode: 'admin.sla.escalated',
                reason: 'sla_breach',
            );

            $this->escalationOps->openFromBreach(
                queueCode: 'disputes',
                targetType: 'dispute_case',
                targetId: (int) $case->id,
                reasonCode: 'sla_breach',
                breachedAt: $now,
                meta: ['source' => 'scheduler'],
            );
        }

        return $targets->count();
    }

    private function escalateWithdrawals(int $breachHours): int
    {
        $cutoff = now()->subHours(max(1, $breachHours));
        $targets = WithdrawalRequest::query()
            ->whereIn('status', [WithdrawalRequestStatus::Requested->value, WithdrawalRequestStatus::UnderReview->value])
            ->whereNull('escalated_at')
            ->where('created_at', '<=', $cutoff)
            ->limit(500)
            ->get();

        if ($targets->isEmpty()) {
            return 0;
        }

        $recipientIds = $this->staffUserIdsByPermission(AdminPermission::WITHDRAWALS_VIEW);
        $now = now();

        foreach ($targets as $request) {
            $request->forceFill([
                'escalated_at' => $now,
                'escalation_reason' => 'sla_breach',
            ])->save();

            $auditorId = $this->resolveAuditActorId($request->assigned_to_user_id, $recipientIds);

            AuditLogWriter::write(
                actorUserId: $auditorId,
                action: 'admin.withdrawal.escalated',
                targetType: 'withdrawal_request',
                targetId: (int) $request->id,
                beforeJson: [
                    'status' => (string) $request->status->value,
                    'escalated_at' => null,
                ],
                afterJson: [
                    'status' => (string) $request->status->value,
                    'escalated_at' => $now->toIso8601String(),
                    'escalation_reason' => 'sla_breach',
                ],
                reasonCode: 'sla_breach',
                correlationId: (string) Str::uuid(),
                ipAddress: null,
                userAgent: 'scheduler:sla-escalations',
            );

            $this->notifyRecipients(
                queueCode: 'withdrawals',
                targetId: (int) $request->id,
                recipientIds: $this->appendAssignedUser($recipientIds, $request->assigned_to_user_id),
                templateCode: 'admin.sla.escalated',
                reason: 'sla_breach',
            );

            $this->escalationOps->openFromBreach(
                queueCode: 'withdrawals',
                targetType: 'withdrawal_request',
                targetId: (int) $request->id,
                reasonCode: 'sla_breach',
                breachedAt: $now,
                meta: ['source' => 'scheduler'],
            );
        }

        return $targets->count();
    }

    /**
     * @return list<int>
     */
    private function staffUserIdsByPermission(string $permissionCode): array
    {
        $ids = DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->leftJoin('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->leftJoin('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereNull('users.deleted_at')
            ->where(function ($q) use ($permissionCode): void {
                $q->where('permissions.code', '=', $permissionCode)
                    ->orWhere('roles.code', '=', RoleCodes::SuperAdmin);
            })
            ->distinct()
            ->pluck('users.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $ids;
    }

    /**
     * @param  list<int>  $recipientIds
     * @return list<int>
     */
    private function appendAssignedUser(array $recipientIds, ?int $assignedUserId): array
    {
        if ($assignedUserId !== null && $assignedUserId > 0) {
            $recipientIds[] = (int) $assignedUserId;
        }

        return array_values(array_unique($recipientIds));
    }

    /**
     * @param  list<int>  $recipientIds
     */
    private function resolveAuditActorId(?int $assignedUserId, array $recipientIds): int
    {
        if ($assignedUserId !== null && $assignedUserId > 0) {
            return (int) $assignedUserId;
        }
        if ($recipientIds !== []) {
            return (int) $recipientIds[0];
        }

        $fallback = User::query()->whereNull('deleted_at')->orderBy('id')->value('id');

        return max(1, (int) ($fallback ?? 1));
    }

    /**
     * @param  list<int>  $recipientIds
     */
    private function notifyRecipients(string $queueCode, int $targetId, array $recipientIds, string $templateCode, string $reason): void
    {
        $now = now();

        foreach ($recipientIds as $recipientId) {
            Notification::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $recipientId,
                'channel' => 'in_app',
                'template_code' => $templateCode,
                'payload_json' => [
                    'queue' => $queueCode,
                    'target_id' => $targetId,
                    'reason' => $reason,
                    'escalated_at' => $now->toIso8601String(),
                    'href' => $this->notificationHref($queueCode, $targetId),
                ],
                'status' => 'sent',
                'sent_at' => $now,
            ]);
        }
    }

    private function notificationHref(string $queueCode, int $targetId): ?string
    {
        return match ($queueCode) {
            'seller_kyc' => route('admin.sellers.kyc.show', ['kyc' => $targetId]),
            'disputes' => route('admin.disputes.show', ['dispute' => $targetId]),
            'withdrawals' => route('admin.withdrawals.show', ['withdrawal' => $targetId]),
            default => route('admin.escalations.show', ['incident' => $targetId]),
        };
    }
}

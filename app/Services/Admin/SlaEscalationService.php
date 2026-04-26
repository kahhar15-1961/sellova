<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Admin\AdminPermission;
use App\Auth\RoleCodes;
use App\Domain\Enums\DisputeCaseStatus;
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
     * @return array{disputes_escalated: int, withdrawals_escalated: int}
     */
    public function run(): array
    {
        $disputeThreshold = (int) config('admin_sla.disputes.breach_hours', 48);
        $withdrawalThreshold = (int) config('admin_sla.withdrawals.breach_hours', 24);

        $disputesEscalated = $this->escalateDisputes($disputeThreshold);
        $withdrawalsEscalated = $this->escalateWithdrawals($withdrawalThreshold);

        return [
            'disputes_escalated' => $disputesEscalated,
            'withdrawals_escalated' => $withdrawalsEscalated,
        ];
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
    private function notifyRecipients(string $queueCode, int $targetId, array $recipientIds): void
    {
        $now = now();

        foreach ($recipientIds as $recipientId) {
            Notification::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $recipientId,
                'channel' => 'in_app',
                'template_code' => 'admin.sla.escalated',
                'payload_json' => [
                    'queue' => $queueCode,
                    'target_id' => $targetId,
                    'reason' => 'sla_breach',
                    'escalated_at' => $now->toIso8601String(),
                ],
                'status' => 'sent',
                'sent_at' => $now,
            ]);
        }
    }
}

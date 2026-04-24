<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Str;

/**
 * Immutable append-only audit rows for compliance-sensitive admin actions.
 */
final class AuditLogWriter
{
    public const TARGET_KYC_VERIFICATION = 'kyc_verification';

    public const ACTION_KYC_CLAIMED = 'kyc.verification.claimed';

    public const ACTION_KYC_REVIEWED = 'kyc.verification.reviewed';

    /**
     * @param  array<string, mixed>  $beforeJson
     * @param  array<string, mixed>  $afterJson
     */
    public static function write(
        int $actorUserId,
        string $action,
        string $targetType,
        int $targetId,
        array $beforeJson,
        array $afterJson,
        ?string $reasonCode,
        ?string $correlationId,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        AuditLog::query()->create([
            'uuid' => (string) Str::uuid(),
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_json' => $beforeJson,
            'after_json' => $afterJson,
            'reason_code' => $reasonCode,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}

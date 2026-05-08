<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Str;

final class AuditService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        ?int $actorId,
        string $actorRole,
        string $action,
        string $targetType,
        int $targetId,
        ?array $before = null,
        ?array $after = null,
        ?string $reasonCode = null,
        ?string $correlationId = null,
        ?string $ipAddress = null,
    ): void {
        AuditLog::query()->create([
            'uuid' => (string) Str::uuid(),
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_json' => array_merge($before ?? [], ['actor_role' => $actorRole]),
            'after_json' => $after,
            'reason_code' => $reasonCode,
            'ip_address' => $ipAddress,
            'user_agent' => null,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}


<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogShowController extends AdminPageController
{
    public function __invoke(AuditLog $auditLog): Response
    {
        $auditLog->load(['actor_user:id,email']);

        return Inertia::render('Admin/AuditLogs/Show', [
            'header' => $this->pageHeader(
                'Audit log #'.$auditLog->id,
                'Immutable detail record for compliance and traceability.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Audit logs', 'href' => route('admin.audit-logs.index')],
                    ['label' => '#'.$auditLog->id],
                ],
            ),
            'record' => [
                'id' => $auditLog->id,
                'uuid' => $auditLog->uuid,
                'time' => $auditLog->created_at?->toIso8601String(),
                'actor' => $auditLog->actor_user?->email,
                'action' => $auditLog->action,
                'target_type' => $auditLog->target_type,
                'target_id' => $auditLog->target_id,
                'reason_code' => $auditLog->reason_code,
                'correlation_id' => $auditLog->correlation_id,
                'ip_address' => $auditLog->ip_address,
                'user_agent' => $auditLog->user_agent,
                'before_json' => $auditLog->before_json,
                'after_json' => $auditLog->after_json,
            ],
            'list_href' => route('admin.audit-logs.index'),
        ]);
    }
}

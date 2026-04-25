<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->auditLogsIndex($request);

        return Inertia::render('Admin/AuditLogs/Index', [
            'header' => $this->pageHeader(
                'Audit logs',
                'Immutable audit trail with actor, action, reason and record drill-down.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Audit logs'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.audit-logs.index'),
            'export_url' => route('admin.audit-logs.export'),
        ]);
    }
}

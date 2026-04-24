<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class AuditLogsController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/AuditLogs/Index', [
            'header' => $this->pageHeader(
                'Audit logs',
                'Immutable staff and system actions — wire to AuditLog model with strict filters.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Audit logs'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}

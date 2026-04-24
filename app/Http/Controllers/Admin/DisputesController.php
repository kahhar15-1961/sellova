<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class DisputesController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Disputes/Index', [
            'header' => $this->pageHeader(
                'Disputes',
                'Case queues, evidence, and adjudication — DisputeService integration next.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Support'],
                    ['label' => 'Disputes'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}

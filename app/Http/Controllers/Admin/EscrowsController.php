<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class EscrowsController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Escrows/Index', [
            'header' => $this->pageHeader(
                'Escrows',
                'Held funds, releases, and state transitions — read-only foundation until audited actions ship.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders & Escrow'],
                    ['label' => 'Escrows'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}

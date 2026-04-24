<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class WalletsController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Wallets/Index', [
            'header' => $this->pageHeader(
                'Wallets & ledger',
                'Balances, holds, and ledger batches — read-first; no financial mutations in this foundation.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Finance'],
                    ['label' => 'Wallets'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}

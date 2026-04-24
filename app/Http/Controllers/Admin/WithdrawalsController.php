<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class WithdrawalsController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Withdrawals/Index', [
            'header' => $this->pageHeader(
                'Withdrawals',
                'Payout review queue — approval flows will require finance permissions + audit hooks.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Finance'],
                    ['label' => 'Withdrawals'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}

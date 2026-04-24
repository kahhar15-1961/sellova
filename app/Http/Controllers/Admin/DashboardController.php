<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'header' => $this->pageHeader(
                'Dashboard',
                'Operational overview for marketplace, escrow, and wallet health.',
                [
                    ['label' => 'Overview'],
                ],
            ),
            'stats' => [
                ['key' => 'orders_open', 'label' => 'Open orders', 'value' => '—', 'hint' => 'Wire to OrderService'],
                ['key' => 'escrow_held', 'label' => 'Escrow held', 'value' => '—', 'hint' => 'Wire to EscrowService'],
                ['key' => 'disputes_open', 'label' => 'Open disputes', 'value' => '—', 'hint' => 'Wire to DisputeService'],
                ['key' => 'withdrawals_pending', 'label' => 'Pending withdrawals', 'value' => '—', 'hint' => 'Wire to WithdrawalService'],
            ],
        ]);
    }
}

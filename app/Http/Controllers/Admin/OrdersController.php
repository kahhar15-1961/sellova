<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class OrdersController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Orders/Index', [
            'header' => $this->pageHeader(
                'Orders',
                'Fulfillment pipeline and buyer/seller timelines — OrderService-backed listings next.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders & Escrow'],
                    ['label' => 'Orders'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}

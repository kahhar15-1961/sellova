<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class SellersController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Sellers/Index', [
            'header' => $this->pageHeader(
                'Sellers & verification',
                'KYC queues, storefront status, and seller risk — foundation for UserSellerService-backed screens.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Sellers'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}

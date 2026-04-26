<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BuyersController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->buyersIndex($request);

        return Inertia::render('Admin/Buyers/Index', [
            'header' => $this->pageHeader(
                'Buyers',
                'Buyer operations workspace with risk, disputes, and order activity visibility.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Buyers'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.buyers.index'),
            'status_options' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'suspended', 'label' => 'Suspended'],
                ['value' => 'closed', 'label' => 'Closed'],
            ],
        ]);
    }
}

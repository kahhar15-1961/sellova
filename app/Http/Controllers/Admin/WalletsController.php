<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WalletsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->walletsIndex($request);

        return Inertia::render('Admin/Wallets/Index', [
            'header' => $this->pageHeader(
                'Wallets & ledger',
                'Wallet operations view with quick balances and hold totals.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Finance'],
                    ['label' => 'Wallets'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.wallets.index'),
            'status_options' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'frozen', 'label' => 'Frozen'],
                ['value' => 'closed', 'label' => 'Closed'],
            ],
        ]);
    }
}

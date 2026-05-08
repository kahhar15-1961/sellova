<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Enums\WalletTopUpRequestStatus;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WalletTopUpsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->walletTopUpRequestsIndex($request);

        return Inertia::render('Admin/WalletTopUps/Index', [
            'header' => $this->pageHeader(
                'Wallet top-ups',
                'Review queue for wallet funding requests before credit is posted to the ledger.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Finance'],
                    ['label' => 'Wallet top-ups'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.wallet-top-ups.index'),
            'status_options' => collect(WalletTopUpRequestStatus::cases())->map(static fn (WalletTopUpRequestStatus $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }
}

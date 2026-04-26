<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Enums\WithdrawalRequestStatus;
use App\Models\User;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WithdrawalsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->lists->withdrawalsIndex($request, $user);

        return Inertia::render('Admin/Withdrawals/Index', [
            'header' => $this->pageHeader(
                'Withdrawals',
                'Payout review queue backed by WithdrawalRequest rows; open a row to approve or reject when permitted.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Finance'],
                    ['label' => 'Withdrawals'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.withdrawals.index'),
            'claim_url_template' => route('admin.withdrawals.claim', ['withdrawal' => '__ID__']),
            'unclaim_url_template' => route('admin.withdrawals.unclaim', ['withdrawal' => '__ID__']),
            'status_options' => collect(WithdrawalRequestStatus::cases())->map(static fn (WithdrawalRequestStatus $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }
}

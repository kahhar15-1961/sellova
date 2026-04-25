<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Enums\DisputeCaseStatus;
use App\Models\User;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DisputesController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->lists->disputesIndex($request, $user);

        return Inertia::render('Admin/Disputes/Index', [
            'header' => $this->pageHeader(
                'Disputes',
                'Case queue with order linkage; open a case to move to review or resolve when escrow is under dispute.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Support'],
                    ['label' => 'Disputes'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.disputes.index'),
            'status_options' => collect(DisputeCaseStatus::cases())->map(static fn (DisputeCaseStatus $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Enums\EscrowState;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EscrowsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->escrowsIndex($request);

        return Inertia::render('Admin/Escrows/Index', [
            'header' => $this->pageHeader(
                'Escrows',
                'Held funds and escrow state per order — read-only operational view.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders & Escrow'],
                    ['label' => 'Escrows'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.escrows.index'),
            'state_options' => collect(EscrowState::cases())->map(static fn (EscrowState $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }
}

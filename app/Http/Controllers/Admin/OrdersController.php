<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Enums\OrderStatus;
use App\Models\User;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrdersController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->lists->ordersIndex($request, $user);

        return Inertia::render('Admin/Orders/Index', [
            'header' => $this->pageHeader(
                'Orders',
                'Fulfillment pipeline: live orders from the database with search and status filters.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders & Escrow'],
                    ['label' => 'Orders'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.orders.index'),
            'status_options' => collect(OrderStatus::cases())->map(static fn (OrderStatus $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderShowController extends AdminPageController
{
    public function __invoke(Request $request, Order $order): Response
    {
        $order->load([
            'buyer:id,email,status',
            'orderItems.seller_profile:id,display_name',
            'escrowAccount',
            'orderStateTransitions' => static fn ($q) => $q->orderByDesc('id')->limit(50),
        ]);

        $items = $order->orderItems->map(function ($it) use ($order): array {
            return [
                'id' => $it->id,
                'title' => $it->title_snapshot ?? '—',
                'seller' => $it->seller_profile?->display_name ?? '—',
                'line_total' => trim(($order->currency ?? '').' '.(string) $it->line_total_snapshot),
                'delivery' => (string) $it->delivery_state,
            ];
        });

        $transitions = $order->orderStateTransitions->map(static fn ($t): array => [
            'from' => (string) ($t->from_state ?? ''),
            'to' => (string) ($t->to_state ?? ''),
            'at' => $t->created_at?->toIso8601String(),
        ]);

        $escrow = $order->escrowAccount;

        return Inertia::render('Admin/Orders/Show', [
            'header' => $this->pageHeader(
                'Order '.$order->order_number,
                'Read-only order, line items, escrow snapshot, and recent state transitions.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders', 'href' => route('admin.orders.index')],
                    ['label' => $order->order_number ?? '#'.$order->id],
                ],
            ),
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'currency' => $order->currency,
                'gross_amount' => (string) $order->gross_amount,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'buyer_email' => $order->buyer?->email,
            ],
            'items' => $items,
            'escrow' => $escrow === null ? null : [
                'state' => $escrow->state->value,
                'held_amount' => (string) $escrow->held_amount,
                'released_amount' => (string) $escrow->released_amount,
                'refunded_amount' => (string) $escrow->refunded_amount,
                'currency' => $escrow->currency,
            ],
            'transitions' => $transitions,
            'list_href' => route('admin.orders.index'),
        ]);
    }
}

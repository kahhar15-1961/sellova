<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\ChatThread;
use App\Models\DigitalDelivery;
use App\Models\DisputeCase;
use App\Models\EscrowAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

final class EscrowShowController extends AdminPageController
{
    public function __invoke(Request $request, EscrowAccount $escrow): Response
    {
        $escrow->load([
            'order.buyer:id,email,status',
            'order.latestDigitalDelivery.files',
            'order.orderItems.seller_profile.user:id,email,status',
            'order.orderItems.product:id,title,base_price,currency,status,seller_profile_id',
            'order.disputeCases.opened_by_user:id,email',
            'escrowEvents' => static fn ($q) => $q->orderByDesc('id')->limit(25),
        ]);

        $order = $escrow->order;
        $sellerProfile = $order?->orderItems->first()?->seller_profile;
        if ($sellerProfile !== null) {
            $sellerProfile->loadMissing(['storefront:id,seller_profile_id,title,is_public']);
        }

        $disputes = ($order?->disputeCases ?? collect())
            ->map(static fn (DisputeCase $d): array => [
                'id' => $d->id,
                'status' => $d->status->value,
                'opened_by' => $d->opened_by_user?->email ?? '—',
                'opened_at' => $d->opened_at?->toIso8601String(),
                'href' => route('admin.disputes.show', $d),
            ])->values()->all();

        $events = $escrow->escrowEvents->map(static fn ($event): array => [
            'id' => $event->id,
            'type' => $event->event_type->value,
            'from_state' => $event->from_state,
            'to_state' => $event->to_state,
            'amount' => (string) $event->amount,
            'reference_type' => $event->reference_type,
            'reference_id' => $event->reference_id,
            'created_at' => $event->created_at?->toIso8601String(),
        ])->values()->all();

        $delivery = $order?->latestDigitalDelivery;
        $escrowThread = $order
            ? ChatThread::query()
                ->with(['messages' => static fn ($q) => $q->latest('id')->limit(20)])
                ->where('order_id', $order->id)
                ->where('purpose', 'escrow')
                ->first()
            : null;

        return Inertia::render('Admin/Escrows/Show', [
            'header' => $this->pageHeader(
                'Escrow #'.$escrow->id,
                'Order-linked escrow state, settlement events, and admin controls.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Escrows', 'href' => route('admin.escrows.index')],
                    ['label' => '#'.$escrow->id],
                ],
            ),
            'escrow' => [
                'id' => $escrow->id,
                'state' => $escrow->state->value,
                'currency' => $escrow->currency,
                'held_amount' => (string) $escrow->held_amount,
                'released_amount' => (string) $escrow->released_amount,
                'refunded_amount' => (string) $escrow->refunded_amount,
                'held_at' => $escrow->held_at?->toIso8601String(),
                'started_at' => $escrow->started_at?->toIso8601String(),
                'expires_at' => $escrow->expires_at?->toIso8601String(),
                'released_at' => $escrow->released_at?->toIso8601String(),
                'auto_release_at' => $escrow->auto_release_at?->toIso8601String(),
                'dispute_deadline_at' => $escrow->dispute_deadline_at?->toIso8601String(),
                'delivery_deadline_at' => $escrow->delivery_deadline_at?->toIso8601String(),
                'closed_at' => $escrow->closed_at?->toIso8601String(),
                'version' => $escrow->version,
                'order' => $order ? [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'placed_at' => $order->placed_at?->toIso8601String(),
                    'delivery_status' => $order->delivery_status,
                    'buyer_review_expires_at' => $order->buyer_review_expires_at?->toIso8601String(),
                    'href' => route('admin.orders.show', $order),
                ] : null,
                'buyer' => $order?->buyer ? [
                    'id' => $order->buyer->id,
                    'email' => $order->buyer->email,
                    'href' => route('admin.buyers.show', $order->buyer),
                ] : null,
                'seller' => $sellerProfile ? [
                    'id' => $sellerProfile->id,
                    'display_name' => $sellerProfile->display_name,
                    'href' => route('admin.seller-profiles.show', $sellerProfile),
                    'storefront' => $sellerProfile->storefront ? [
                        'title' => $sellerProfile->storefront->title,
                        'is_public' => (bool) $sellerProfile->storefront->is_public,
                    ] : null,
                ] : null,
            ],
            'disputes' => $disputes,
            'events' => $events,
            'delivery' => $delivery instanceof DigitalDelivery ? [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'version' => $delivery->version,
                'note' => $delivery->delivery_note,
                'external_url' => $delivery->external_url,
                'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                'files' => $delivery->files->map(static fn ($file): array => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size_bytes' => (int) $file->size_bytes,
                    'download_url' => URL::temporarySignedRoute('web.actions.orders.delivery-files.download', now()->addMinutes(20), ['digitalDeliveryFile' => $file->id]),
                ])->values()->all(),
            ] : null,
            'chat' => $escrowThread ? [
                'thread_id' => (int) $escrowThread->id,
                'messages' => $escrowThread->messages
                    ->sortBy('id')
                    ->map(static fn ($message): array => [
                        'id' => (int) $message->id,
                        'sender_role' => (string) ($message->sender_role ?? 'system'),
                        'body' => (string) ($message->body ?? ''),
                        'marker_type' => (string) ($message->marker_type ?? ''),
                        'created_at' => $message->created_at?->toIso8601String(),
                    ])->values()->all(),
            ] : null,
            'can_manage' => (bool) ($request->user()?->hasPermissionCode(AdminPermission::ESCROWS_MANAGE) ?? false),
            'action_url' => route('admin.escrows.action', $escrow),
            'list_href' => route('admin.escrows.index'),
            'reason_codes' => \App\Support\AdminReasonCatalog::escrowActionCodes(),
        ]);
    }
}

<?php

namespace App\Services\Order;

use App\Domain\Enums\ProductType;
use App\Models\DigitalDeliveryFile;
use App\Models\Order;
use App\Services\TimeoutAutomation\TimeoutAutomationService;
use Illuminate\Support\Facades\URL;

class EscrowOrderDetailService
{
    public function __construct(
        private readonly OrderStatusService $statuses = new OrderStatusService(),
        private readonly OrderMessageService $messages = new OrderMessageService(),
    ) {
    }

    public function build(Order $order, int $viewerUserId, bool $isAdmin = false): array
    {
        $order->loadMissing([
            'buyer',
            'seller',
            'primaryProduct',
            'orderItems',
            'escrowAccount.escrowEvents',
            'orderStateTransitions',
            'disputeCases',
            'latestDigitalDelivery.files',
        ]);

        $productType = ProductType::normalize((string) ($order->product_type ?? 'physical'));
        $timerState = (new TimeoutAutomationService())->timerState($order);
        $delivery = $order->latestDigitalDelivery;
        $files = $delivery?->files ?? collect();

        $canDownloadFiles = $this->statuses->availableActions($order, $viewerUserId, $isAdmin)['download_delivery_files'] ?? false;

        return [
            'order' => [
                'id' => (int) $order->id,
                'order_number' => (string) ($order->order_number ?? 'ORD-'.$order->id),
                'status' => (string) $order->status->value,
                'ui_status' => $this->uiStatus($order),
                'placed_at' => $order->placed_at?->toIso8601String(),
                'completed_at' => $order->completed_at?->toIso8601String(),
                'currency' => (string) ($order->currency ?? 'BDT'),
                'subtotal' => (string) $order->gross_amount,
                'discount' => (string) $order->discount_amount,
                'tax' => '0.0000',
                'delivery_fee' => $productType->requiresDeliveryChat() ? '0.0000' : (string) $order->fee_amount,
                'escrow_fee' => (string) ($order->escrow_fee ?? '0.0000'),
                'total_paid' => (string) $order->net_amount,
                'product_type' => $productType->value,
                'delivery_status' => (string) ($order->delivery_status ?? ($delivery?->status ?? 'pending')),
                'delivery_note' => $order->delivery_note,
                'delivery_version' => $order->delivery_version,
                'delivery_files_count' => (int) ($order->delivery_files_count ?? $files->count()),
                'buyer_confirmed_at' => $order->buyer_confirmed_at?->toIso8601String(),
                'invoice_url' => route('web.buyer.view', ['view' => 'order-details']).'?order='.(int) $order->id,
            ],
            'buyer' => [
                'id' => (int) $order->buyer_user_id,
                'name' => (string) ($order->buyer?->name ?? $order->buyer?->email ?? 'Buyer'),
                'email' => (string) ($order->buyer?->email ?? ''),
            ],
            'seller' => [
                'id' => (int) ($order->seller_user_id ?? 0),
                'name' => (string) ($order->seller?->display_name ?? $order->seller?->name ?? $order->seller?->email ?? 'Seller'),
                'email' => (string) ($order->seller?->email ?? ''),
            ],
            'items' => $order->orderItems->map(fn ($item): array => [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'title' => (string) $item->title_snapshot,
                'quantity' => (int) $item->quantity,
                'subtotal' => (string) $item->line_total_snapshot,
                'product_type' => (string) $item->product_type_snapshot,
                'delivery_state' => (string) $item->delivery_state,
                'image_url' => (string) ($order->primaryProduct?->image_url ?? ''),
            ])->values()->all(),
            'escrow' => [
                'status' => (string) ($order->escrow_status ?: ($order->escrowAccount?->state?->value ?? 'pending')),
                'amount' => (string) ($order->escrow_amount ?: $order->net_amount),
                'fee' => (string) ($order->escrow_fee ?? '0.0000'),
                'started_at' => $order->escrow_started_at?->toIso8601String() ?? $order->escrowAccount?->held_at?->toIso8601String(),
                'expires_at' => $order->escrow_expires_at?->toIso8601String() ?? $timerState['buyer_review_expires_at'] ?? $timerState['seller_deadline_at'] ?? null,
                'released_at' => $order->escrow_released_at?->toIso8601String(),
                'auto_release_at' => $order->escrow_auto_release_at?->toIso8601String() ?? $order->auto_release_at?->toIso8601String(),
                'release_method' => $order->escrow_release_method,
                'dispute_deadline_at' => $order->dispute_deadline_at?->toIso8601String() ?? $order->buyer_review_expires_at?->toIso8601String(),
                'delivery_deadline_at' => $order->delivery_deadline_at?->toIso8601String() ?? $order->seller_deadline_at?->toIso8601String(),
                'timer' => [
                    'server_now' => now()->toIso8601String(),
                    'seconds_remaining' => $timerState['seconds_remaining'] ?? null,
                    'warning' => ($timerState['seconds_remaining'] ?? 0) > 0 && ($timerState['seconds_remaining'] ?? 0) <= 900,
                    'expired' => ($timerState['seconds_remaining'] ?? 0) === 0 && ($timerState['next_event_at'] ?? null) !== null,
                    'next_event_at' => $timerState['next_event_at'] ?? null,
                    'active_timer' => $timerState['active_timer'] ?? null,
                    'expiry_action' => $timerState['expiry_action'] ?? null,
                ],
            ],
            'delivery' => [
                'id' => $delivery?->id,
                'status' => (string) ($delivery?->status ?? ($order->delivery_status ?: 'pending')),
                'message' => $delivery?->delivery_note,
                'version' => $delivery?->version,
                'external_url' => $delivery?->external_url,
                'delivered_at' => $delivery?->delivered_at?->toIso8601String(),
                'buyer_confirmed_at' => $delivery?->buyer_confirmed_at?->toIso8601String() ?? $order->buyer_confirmed_at?->toIso8601String(),
                'files' => $files->map(function (DigitalDeliveryFile $file) use ($canDownloadFiles): array {
                    return [
                        'id' => (int) $file->id,
                        'name' => (string) $file->original_name,
                        'mime_type' => (string) ($file->mime_type ?? ''),
                        'size_bytes' => (int) $file->size_bytes,
                        'download_url' => $canDownloadFiles
                            ? URL::temporarySignedRoute('web.actions.orders.delivery-files.download', now()->addMinutes(20), ['digitalDeliveryFile' => $file->id])
                            : null,
                    ];
                })->values()->all(),
            ],
            'messages' => $this->messages->listMessages($order, $viewerUserId, $isAdmin),
            'chat' => [
                'thread_id' => $this->messages->threadId($order),
            ],
            'timeline' => $this->statuses->timeline($order),
            'activity_timeline' => $this->activityTimeline($order),
            'available_actions' => $this->statuses->availableActions($order, $viewerUserId, $isAdmin),
            'permissions' => [
                'is_buyer' => (int) $order->buyer_user_id === $viewerUserId,
                'is_seller' => (int) ($order->seller_user_id ?? 0) === $viewerUserId,
                'is_admin' => $isAdmin,
            ],
        ];
    }

    private function uiStatus(Order $order): string
    {
        return match ((string) $order->status->value) {
            'escrow_funded' => 'escrow_held',
            'processing' => 'seller_preparing',
            'delivery_submitted', 'buyer_review' => 'buyer_reviewing',
            default => (string) $order->status->value,
        };
    }

    private function activityTimeline(Order $order): array
    {
        $items = $order->orderStateTransitions
            ->sortByDesc('created_at')
            ->map(static fn ($transition): array => [
                'id' => 'transition-'.$transition->id,
                'type' => 'status',
                'title' => str_replace('_', ' ', (string) $transition->to_state),
                'body' => (string) ($transition->reason_code ?? ''),
                'created_at' => $transition->created_at?->toIso8601String(),
            ]);

        $escrowItems = $order->escrowAccount?->escrowEvents?->sortByDesc('created_at')->map(static fn ($event): array => [
            'id' => 'escrow-'.$event->id,
            'type' => 'escrow',
            'title' => str_replace('_', ' ', (string) $event->event_type->value),
            'body' => (string) $event->amount.' '.(string) $event->currency,
            'created_at' => $event->created_at?->toIso8601String(),
        ]) ?? collect();

        return $items
            ->concat($escrowItems)
            ->sortByDesc(static fn (array $item): int => strtotime((string) ($item['created_at'] ?? '')) ?: 0)
            ->values()
            ->all();
    }
}

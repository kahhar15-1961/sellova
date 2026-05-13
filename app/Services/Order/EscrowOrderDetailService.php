<?php

namespace App\Services\Order;

use App\Domain\Enums\ProductType;
use App\Models\BuyerReview;
use App\Models\DigitalDeliveryFile;
use App\Models\Order;
use App\Models\Review;
use App\Services\TimeoutAutomation\TimeoutAutomationService;
use Illuminate\Support\Facades\URL;

class EscrowOrderDetailService
{
    public function __construct(
        private readonly OrderStatusService $statuses = new OrderStatusService(),
        private readonly OrderMessageService $messages = new OrderMessageService(),
        private readonly OrderTypeResolver $types = new OrderTypeResolver(),
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

        $flow = $this->types->resolve($order);
        $productType = ProductType::normalize((string) ($order->product_type ?? ($flow['product_types'][0] ?? 'digital')));
        $timerState = (new TimeoutAutomationService())->timerState($order);
        $delivery = $order->latestDigitalDelivery;
        $files = $delivery?->files ?? collect();
        $isPhysical = $flow['flow_type'] === 'physical_delivery';
        $isDigitalEscrow = $flow['flow_type'] === 'digital_escrow';
        $deliveryStatus = $this->deliveryStatusForDetail($order, $delivery, $isPhysical);

        $canDownloadFiles = $this->statuses->availableActions($order, $viewerUserId, $isAdmin)['download_delivery_files'] ?? false;
        $physicalTrackingUrl = $isPhysical ? $this->realTrackingUrl($order->tracking_url) : null;
        $context = $isAdmin
            ? 'admin'
            : ((int) ($order->seller_user_id ?? 0) === $viewerUserId ? 'seller' : 'buyer');
        $availableActions = $this->statuses->availableActions($order, $viewerUserId, $isAdmin);
        $status = $order->status instanceof \BackedEnum ? (string) $order->status->value : (string) $order->status;
        $sellerProfileId = (int) ($order->orderItems->first()?->seller_profile_id ?? 0);
        $isCompleted = $status === 'completed' || $order->completed_at !== null || (string) $order->escrow_status === 'released';
        $rawEscrowStatus = (string) ($order->escrow_status ?: ($order->escrowAccount?->state?->value ?? 'pending'));
        $detailEscrowStatus = $isCompleted && ! in_array($rawEscrowStatus, ['disputed', 'refunded', 'cancelled'], true)
            ? 'released'
            : $rawEscrowStatus;
        $hasActiveTimer = ! $isCompleted && ($timerState['seconds_remaining'] ?? null) !== null;
        $review = null;
        $buyerReview = null;
        $buyerReviewStats = [
            'average_rating' => null,
            'total' => 0,
            'recent' => [],
        ];
        if ($context === 'buyer') {
            $orderItemIds = $order->orderItems->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            if ($orderItemIds !== []) {
                $review = Review::query()
                    ->whereIn('order_item_id', $orderItemIds)
                    ->where('buyer_user_id', $viewerUserId)
                    ->latest('id')
                    ->first();
            }
        }
        if ($context === 'seller') {
            $sellerProfileId = (int) ($order->orderItems->first()?->seller_profile_id ?? 0);
            if ($sellerProfileId > 0) {
                $buyerReview = BuyerReview::query()
                    ->where('order_id', (int) $order->id)
                    ->where('seller_profile_id', $sellerProfileId)
                    ->first();
            }

            $baseBuyerReviews = BuyerReview::query()
                ->where('buyer_user_id', (int) $order->buyer_user_id)
                ->where('status', 'visible');
            $totalBuyerReviews = (int) (clone $baseBuyerReviews)->count();
            $averageBuyerRating = $totalBuyerReviews > 0 ? (float) (clone $baseBuyerReviews)->avg('rating') : null;
            $recentBuyerReviews = (clone $baseBuyerReviews)
                ->with(['seller_profile', 'order'])
                ->latest('id')
                ->take(2)
                ->get()
                ->map(static fn (BuyerReview $review): array => [
                    'id' => (int) $review->id,
                    'rating' => (int) $review->rating,
                    'comment' => (string) ($review->comment ?? ''),
                    'seller' => (string) ($review->seller_profile?->display_name ?? 'Seller'),
                    'order_number' => (string) ($review->order?->order_number ?? ''),
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->values()->all();

            $buyerReviewStats = [
                'average_rating' => $averageBuyerRating !== null ? round($averageBuyerRating, 1) : null,
                'total' => $totalBuyerReviews,
                'recent' => $recentBuyerReviews,
            ];
        }

        return [
            'context' => $context,
            'order_type' => $flow['order_type'],
            'flow_type' => $flow['flow_type'],
            'product_types' => $flow['product_types'],
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
                'order_type' => $flow['order_type'],
                'order_flow_type' => $flow['flow_type'],
                'delivery_status' => $deliveryStatus,
                'delivery_note' => $order->delivery_note,
                'delivery_version' => $order->delivery_version,
                'delivery_files_count' => (int) ($order->delivery_files_count ?? $files->count()),
                'buyer_confirmed_at' => $order->buyer_confirmed_at?->toIso8601String(),
                'invoice_url' => $context === 'seller' ? '/seller/orders/'.(int) $order->id : '/buyer/orders/'.(int) $order->id,
            ],
            'buyer' => [
                'id' => (int) $order->buyer_user_id,
                'name' => (string) ($order->buyer?->display_name ?: 'Buyer #'.(int) $order->buyer_user_id),
                'email' => $isAdmin ? (string) ($order->buyer?->email ?? '') : $this->maskEmail($order->buyer?->email),
                'profile_url' => $context === 'seller' || $isAdmin ? '/profiles/buyers/'.(int) $order->buyer_user_id : null,
            ],
            'seller' => [
                'id' => (int) ($order->seller_user_id ?? 0),
                'seller_profile_id' => $sellerProfileId,
                'name' => (string) ($order->seller?->display_name ?: 'Seller #'.(int) ($order->seller_user_id ?? 0)),
                'email' => $isAdmin ? (string) ($order->seller?->email ?? '') : $this->maskEmail($order->seller?->email),
                'profile_url' => $sellerProfileId > 0 ? '/profiles/sellers/'.$sellerProfileId : null,
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
                'status' => $detailEscrowStatus,
                'amount' => (string) ($order->escrow_amount ?: $order->net_amount),
                'fee' => (string) ($order->escrow_fee ?? '0.0000'),
                'started_at' => $order->escrow_started_at?->toIso8601String() ?? $order->escrowAccount?->held_at?->toIso8601String(),
                'expires_at' => $isCompleted ? null : ($order->escrow_expires_at?->toIso8601String() ?? $timerState['buyer_review_expires_at'] ?? $timerState['seller_deadline_at'] ?? null),
                'released_at' => $order->escrow_released_at?->toIso8601String(),
                'auto_release_at' => $order->escrow_auto_release_at?->toIso8601String() ?? $order->auto_release_at?->toIso8601String(),
                'release_method' => $order->escrow_release_method,
                'dispute_deadline_at' => $order->dispute_deadline_at?->toIso8601String() ?? $order->buyer_review_expires_at?->toIso8601String(),
                'delivery_deadline_at' => $order->delivery_deadline_at?->toIso8601String() ?? $order->seller_deadline_at?->toIso8601String(),
                'timer' => [
                    'server_now' => now()->toIso8601String(),
                    'seconds_remaining' => $hasActiveTimer ? (int) floor((float) $timerState['seconds_remaining']) : null,
                    'warning' => $hasActiveTimer && ($timerState['seconds_remaining'] ?? 0) > 0 && ($timerState['seconds_remaining'] ?? 0) <= 900,
                    'expired' => $hasActiveTimer && ($timerState['seconds_remaining'] ?? 0) === 0 && ($timerState['next_event_at'] ?? null) !== null,
                    'next_event_at' => $hasActiveTimer ? ($timerState['next_event_at'] ?? null) : null,
                    'active_timer' => $hasActiveTimer ? ($timerState['active_timer'] ?? null) : null,
                    'expiry_action' => $hasActiveTimer ? ($timerState['expiry_action'] ?? null) : null,
                ],
            ],
            'delivery' => [
                'id' => $delivery?->id,
                'status' => $deliveryStatus,
                'message' => $delivery?->delivery_note ?? ($isPhysical ? $order->shipping_note : $order->delivery_note),
                'version' => $delivery?->version ?? ($isPhysical ? $order->tracking_id : $order->delivery_version),
                'external_url' => $delivery?->external_url ?? $physicalTrackingUrl,
                'delivered_at' => $delivery?->delivered_at?->toIso8601String() ?? ($isPhysical ? $order->shipped_at?->toIso8601String() : null),
                'buyer_confirmed_at' => $delivery?->buyer_confirmed_at?->toIso8601String() ?? $order->buyer_confirmed_at?->toIso8601String(),
                'files_count' => (int) ($order->delivery_files_count ?? $files->count()),
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
            'shipment' => $isPhysical ? [
                'courier_name' => (string) ($order->courier_company ?? ''),
                'tracking_number' => (string) ($order->tracking_id ?? ''),
                'tracking_url' => $physicalTrackingUrl,
                'shipping_status' => (string) ($order->delivery_status ?? ($order->shipped_at ? 'shipped' : 'pending')),
                'shipped_at' => $order->shipped_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'shipping_note' => $order->shipping_note,
                'shipping_address' => [
                    'recipient_name' => (string) ($order->shipping_recipient_name ?? ''),
                    'address_line' => (string) ($order->shipping_address_line ?? ''),
                    'phone' => (string) ($order->shipping_phone ?? ''),
                    'method' => (string) ($order->shipping_method ?? ''),
                ],
            ] : null,
            'messages' => $this->messages->listMessages($order, $viewerUserId, $isAdmin),
            'chat' => [
                'thread_id' => $this->messages->threadId($order),
            ],
            'review' => [
                'can_review' => $context === 'buyer' && $isCompleted,
                'needs_review' => $context === 'buyer' && $isCompleted && ! $review instanceof Review,
                'has_review' => $review instanceof Review,
                'current' => $review instanceof Review ? [
                    'id' => (int) $review->id,
                    'rating' => (int) $review->rating,
                    'comment' => (string) ($review->comment ?? ''),
                    'status' => (string) $review->status,
                    'created_at' => $review->created_at?->toIso8601String(),
                ] : null,
            ],
            'buyer_review' => [
                'can_review' => $context === 'seller' && $isCompleted,
                'needs_review' => $context === 'seller' && $isCompleted && ! $buyerReview instanceof BuyerReview,
                'has_review' => $buyerReview instanceof BuyerReview,
                'current' => $buyerReview instanceof BuyerReview ? [
                    'id' => (int) $buyerReview->id,
                    'rating' => (int) $buyerReview->rating,
                    'comment' => (string) ($buyerReview->comment ?? ''),
                    'status' => (string) $buyerReview->status,
                    'created_at' => $buyerReview->created_at?->toIso8601String(),
                ] : null,
                'summary' => $buyerReviewStats,
            ],
            'timeline' => $this->statuses->timeline($order),
            'activity_timeline' => $this->activityTimeline($order),
            'available_actions' => array_merge($availableActions, [
                'submit_delivery' => (bool) ($availableActions['submit_delivery'] ?? $availableActions['submit_shipment'] ?? $availableActions['submit_digital_delivery'] ?? $availableActions['upload_delivery_files'] ?? false),
                'message_buyer' => $context === 'seller',
                'message_seller' => $context === 'buyer',
                'view_escrow' => true,
            ]),
            'permissions' => [
                'is_buyer' => (int) $order->buyer_user_id === $viewerUserId,
                'is_seller' => (int) ($order->seller_user_id ?? 0) === $viewerUserId,
                'is_admin' => $isAdmin,
                'can_release_funds' => $context === 'buyer' && (bool) ($availableActions['release_funds'] ?? false),
                'can_open_dispute' => $context === 'buyer' && (bool) ($availableActions['open_dispute'] ?? false),
                'can_download_delivery' => $context === 'buyer' && (bool) ($availableActions['download_delivery_files'] ?? false),
                'can_submit_delivery' => $context === 'seller' && (bool) ($availableActions['submit_delivery'] ?? $availableActions['submit_shipment'] ?? $availableActions['submit_digital_delivery'] ?? $availableActions['upload_delivery_files'] ?? false),
                'can_update_shipping' => $context === 'seller' && $isPhysical,
                'can_chat_with_buyer' => $context === 'seller',
                'can_chat_with_seller' => $context === 'buyer',
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

    private function deliveryStatusForDetail(Order $order, mixed $delivery, bool $isPhysical): string
    {
        if ($delivery !== null && trim((string) $delivery->status) !== '') {
            return (string) $delivery->status;
        }

        $status = strtolower((string) ($order->delivery_status ?? 'pending'));
        if (! $isPhysical && in_array($status, ['', 'preparing'], true)) {
            return 'pending';
        }

        if ($isPhysical && $order->shipped_at !== null && ! in_array($status, ['delivered', 'accepted'], true)) {
            return 'shipped';
        }

        return $status !== '' ? $status : 'pending';
    }

    private function realTrackingUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        return str_starts_with($url, 'https://tracking.sellova.com/') ? null : $url;
    }

    private function maskEmail(?string $email): string
    {
        $email = (string) $email;
        if (! str_contains($email, '@')) {
            return '';
        }

        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 1).str_repeat('*', max(2, strlen($name) - 1)).'@'.$domain;
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

        $deliveryItems = collect();
        if ($order->delivery_submitted_at !== null || $order->shipped_at !== null || $order->delivered_at !== null) {
            $deliveryItems->push([
                'id' => 'delivery-submitted-'.$order->id,
                'type' => 'delivery',
                'title' => 'Delivery submitted',
                'body' => (string) ($order->delivery_note ?: $order->shipping_note ?: 'Seller submitted delivery proof.'),
                'created_at' => ($order->delivery_submitted_at ?? $order->shipped_at ?? $order->delivered_at)?->toIso8601String(),
            ]);
        }

        return $items
            ->concat($escrowItems)
            ->concat($deliveryItems)
            ->sortByDesc(static fn (array $item): int => strtotime((string) ($item['created_at'] ?? '')) ?: 0)
            ->values()
            ->all();
    }
}

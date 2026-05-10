<?php

namespace App\Services\Order;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\ProductType;
use App\Models\Order;

class OrderStatusService
{
    public function timeline(Order $order): array
    {
        $status = (string) $order->status->value;
        $deliveredAt = $order->delivered_at ?? $order->delivery_submitted_at;
        $completedAt = $order->completed_at ?? $order->escrow_released_at;

        return [
            [
                'key' => 'order_placed',
                'label' => 'Order Placed',
                'at' => $order->placed_at?->toIso8601String(),
                'state' => $order->placed_at ? 'completed' : 'pending',
            ],
            [
                'key' => 'escrow_held',
                'label' => 'Escrow Held',
                'at' => $order->escrow_started_at?->toIso8601String(),
                'state' => in_array($status, ['escrow_funded', 'processing', 'delivery_submitted', 'buyer_review', 'completed', 'disputed'], true) ? 'completed' : 'pending',
            ],
            [
                'key' => 'delivered',
                'label' => 'Delivered',
                'at' => $deliveredAt?->toIso8601String(),
                'state' => $deliveredAt ? 'completed' : (in_array($status, ['delivery_submitted', 'buyer_review'], true) ? 'active' : 'pending'),
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'at' => $completedAt?->toIso8601String(),
                'state' => $completedAt ? 'completed' : (in_array($status, ['buyer_review'], true) ? 'active' : 'pending'),
            ],
        ];
    }

    public function availableActions(Order $order, int $viewerUserId, bool $isAdmin = false): array
    {
        $isBuyer = (int) $order->buyer_user_id === $viewerUserId;
        $isSeller = (int) ($order->seller_user_id ?? 0) === $viewerUserId;
        $isDigital = ProductType::normalize((string) $order->product_type)->requiresDeliveryChat();
        $hasDelivery = (string) ($order->delivery_status ?? '') !== '' && (string) ($order->delivery_status ?? '') !== 'pending';
        $status = (string) $order->status->value;
        $disputed = $status === OrderStatus::Disputed->value;
        $completed = $status === OrderStatus::Completed->value;
        $autoReleased = (string) ($order->escrow_release_method ?? '') === 'auto_release';

        return [
            'release_funds' => $isBuyer && ! $disputed && ! $completed && ! $autoReleased && $hasDelivery,
            'open_dispute' => $isBuyer && ! $disputed && ! $completed && ! $autoReleased,
            'submit_delivery' => $isSeller && $isDigital && ! $completed && ! $disputed,
            'request_revision' => $isBuyer && $hasDelivery && ! $completed && ! $disputed,
            'send_message' => ($isBuyer || $isSeller || $isAdmin) && ! in_array($status, ['cancelled', 'refunded'], true),
            'download_delivery_files' => ($isBuyer || $isSeller || $isAdmin) && $hasDelivery && in_array($status, ['escrow_funded', 'processing', 'delivery_submitted', 'buyer_review', 'completed', 'disputed'], true),
            'admin_release' => $isAdmin && ! $completed,
            'admin_refund' => $isAdmin && ! $completed,
            'admin_extend_deadline' => $isAdmin,
        ];
    }
}

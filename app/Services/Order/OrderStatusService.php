<?php

namespace App\Services\Order;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\ProductType;
use App\Models\Order;

class OrderStatusService
{
    public function __construct(
        private readonly OrderTimelineService $timelines = new OrderTimelineService(),
        private readonly OrderTypeResolver $types = new OrderTypeResolver(),
    ) {
    }

    public function timeline(Order $order): array
    {
        return $this->timelines->build($order);
    }

    public function availableActions(Order $order, int $viewerUserId, bool $isAdmin = false): array
    {
        $isBuyer = (int) $order->buyer_user_id === $viewerUserId;
        $isSeller = (int) ($order->seller_user_id ?? 0) === $viewerUserId;
        $flow = $this->types->resolve($order);
        $isDigital = $flow['flow_type'] === 'digital_escrow';
        $isPhysical = $flow['flow_type'] === 'physical_delivery';
        $deliveryStatus = strtolower((string) ($order->delivery_status ?? 'pending'));
        $hasDelivery = in_array($deliveryStatus, ['delivered', 'accepted'], true)
            || in_array((string) $order->status->value, ['delivery_submitted', 'buyer_review', 'completed'], true);
        $status = (string) $order->status->value;
        $disputed = $status === OrderStatus::Disputed->value;
        $completed = $status === OrderStatus::Completed->value;
        $autoReleased = (string) ($order->escrow_release_method ?? '') === 'auto_release';

        return [
            'release_funds' => $isBuyer && ! $disputed && ! $completed && ! $autoReleased && $hasDelivery,
            'open_dispute' => $isBuyer && ! $disputed && ! $completed && ! $autoReleased,
            'submit_delivery' => $isSeller && $isDigital && ! $completed && ! $disputed,
            'submit_shipment' => $isSeller && $isPhysical && ! $completed && ! $disputed && ! $hasDelivery,
            'request_revision' => $isBuyer && $hasDelivery && ! $completed && ! $disputed,
            'send_message' => ($isBuyer || $isSeller || $isAdmin) && ! in_array($status, ['cancelled', 'refunded'], true),
            'download_delivery_files' => ($isBuyer || $isSeller || $isAdmin) && $hasDelivery && in_array($status, ['escrow_funded', 'processing', 'delivery_submitted', 'buyer_review', 'completed', 'disputed'], true),
            'admin_release' => $isAdmin && ! $completed,
            'admin_refund' => $isAdmin && ! $completed,
            'admin_extend_deadline' => $isAdmin,
        ];
    }
}

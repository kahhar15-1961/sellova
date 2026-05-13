<?php

namespace App\Services\Order;

use App\Domain\Enums\OrderStatus;
use App\Models\Order;

class OrderTimelineService
{
    public function __construct(
        private readonly OrderTypeResolver $types = new OrderTypeResolver(),
    ) {
    }

    /**
     * @return list<array{key: string, label: string, description: string, at: ?string, state: string, actor: ?string}>
     */
    public function build(Order $order): array
    {
        $flow = $this->types->resolve($order)['flow_type'];

        return match ($flow) {
            'physical_delivery' => $this->physicalTimeline($order),
            'mixed_order' => $this->mixedTimeline($order),
            default => $this->digitalTimeline($order),
        };
    }

    private function digitalTimeline(Order $order): array
    {
        $status = (string) $order->status->value;
        $deliveryStatus = strtolower((string) ($order->delivery_status ?? 'pending'));
        $deliverySubmitted = in_array($deliveryStatus, ['delivered', 'accepted'], true)
            || in_array($status, [OrderStatus::DeliverySubmitted->value, OrderStatus::BuyerReview->value, OrderStatus::Completed->value], true);
        $completedAt = $order->completed_at ?? $order->escrow_released_at;

        return [
            $this->step('order_placed', 'Order Placed', 'Checkout was created.', $order->placed_at, $order->placed_at ? 'completed' : 'pending', 'buyer'),
            $this->step('escrow_held', 'Escrow Held', 'Payment is secured and held safely.', $order->escrow_started_at, $deliverySubmitted || $completedAt ? 'completed' : 'active', 'system'),
            $this->step('seller_preparing', 'Seller Preparing', 'Seller is preparing files, access, or service handoff.', $order->buyer_review_started_at ?? $order->updated_at, $deliverySubmitted || $completedAt ? 'completed' : 'pending', 'seller'),
            $this->step('delivered', 'Submitted', 'Seller submitted the digital delivery.', $order->delivery_submitted_at ?? $order->delivered_at, $deliverySubmitted || $completedAt ? 'completed' : 'pending', 'seller'),
            $this->step('buyer_reviewing', 'Buyer Reviewing', 'Buyer can review, approve, dispute, or request revision.', $order->buyer_review_started_at ?? $order->delivery_submitted_at, $completedAt ? 'completed' : ($deliverySubmitted ? 'active' : 'pending'), 'buyer'),
            $this->step('completed', 'Completed', 'Escrow was released and the order closed.', $completedAt, $completedAt ? 'completed' : 'pending', 'system'),
        ];
    }

    private function physicalTimeline(Order $order): array
    {
        $status = (string) $order->status->value;
        $completedAt = $order->completed_at ?? $order->escrow_released_at;
        $hasShipped = $order->shipped_at !== null || trim((string) $order->tracking_id) !== '';
        $hasDelivered = $order->delivered_at !== null || strtolower((string) $order->delivery_status) === 'delivered';

        return [
            $this->step('order_placed', 'Order Placed', 'Checkout was created.', $order->placed_at, $order->placed_at ? 'completed' : 'pending', 'buyer'),
            $this->step('payment_confirmed', 'Payment Confirmed', 'Payment is confirmed for fulfillment.', $order->escrow_started_at ?? $order->placed_at, in_array($status, [OrderStatus::EscrowFunded->value, OrderStatus::Processing->value, OrderStatus::BuyerReview->value, OrderStatus::Completed->value], true) ? 'completed' : 'pending', 'system'),
            $this->step('processing', 'Processing', 'Seller is preparing the package.', $order->updated_at, $hasShipped || $hasDelivered || $completedAt ? 'completed' : (in_array($status, [OrderStatus::EscrowFunded->value, OrderStatus::Processing->value], true) ? 'active' : 'pending'), 'seller'),
            $this->step('shipped', 'Shipped', 'Courier and tracking details are available.', $order->shipped_at, $hasDelivered || $completedAt ? 'completed' : ($hasShipped ? 'active' : 'pending'), 'seller'),
            $this->step('delivered', 'Delivered', 'Buyer received the physical item.', $order->delivered_at, $completedAt ? 'completed' : ($hasDelivered ? 'active' : 'pending'), 'buyer'),
            $this->step('completed', 'Completed', 'Order was completed.', $completedAt, $completedAt ? 'completed' : 'pending', 'system'),
        ];
    }

    private function mixedTimeline(Order $order): array
    {
        $timeline = $this->digitalTimeline($order);
        $timeline[1]['label'] = 'Payment Secured';
        $timeline[2]['label'] = 'Fulfillment Groups';
        $timeline[2]['description'] = 'Digital and physical items follow separate fulfillment sections.';

        return $timeline;
    }

    private function step(string $key, string $label, string $description, mixed $at, string $state, ?string $actor): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'at' => $at?->toIso8601String(),
            'state' => $state,
            'actor' => $actor,
        ];
    }
}

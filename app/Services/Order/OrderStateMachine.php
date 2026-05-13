<?php

namespace App\Services\Order;

use App\Domain\Enums\OrderStatus;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Models\Order;
use App\Models\OrderStateTransition;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Str;

final class OrderStateMachine
{
    public function __construct(
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService(),
    )
    {
    }

    /**
     * @return array<string, list<string>>
     */
    private function transitions(): array
    {
        return [
            OrderStatus::Draft->value => [OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value],
            OrderStatus::PendingPayment->value => [OrderStatus::EscrowFunded->value, OrderStatus::Cancelled->value],
            OrderStatus::PaidInEscrow->value => [OrderStatus::Processing->value, OrderStatus::Disputed->value, OrderStatus::Cancelled->value],
            OrderStatus::EscrowFunded->value => [OrderStatus::Processing->value, OrderStatus::Disputed->value, OrderStatus::Cancelled->value],
            OrderStatus::Processing->value => [OrderStatus::DeliverySubmitted->value, OrderStatus::BuyerReview->value, OrderStatus::Disputed->value],
            OrderStatus::DeliverySubmitted->value => [OrderStatus::BuyerReview->value, OrderStatus::Completed->value, OrderStatus::Disputed->value],
            OrderStatus::BuyerReview->value => [OrderStatus::Completed->value, OrderStatus::Disputed->value],
            OrderStatus::ShippedOrDelivered->value => [OrderStatus::Completed->value, OrderStatus::Disputed->value],
            OrderStatus::Disputed->value => [OrderStatus::Completed->value, OrderStatus::Refunded->value, OrderStatus::EscrowFunded->value],
        ];
    }

    public function transition(
        Order $order,
        OrderStatus $to,
        string $reasonCode,
        ?int $actorUserId,
        ?string $correlationId = null,
        array $attributes = [],
    ): void {
        $from = $order->status;
        $before = [
            'status' => $from->value,
            'fulfillment_state' => $order->fulfillment_state,
        ];
        if ($from !== $to && ! in_array($to->value, $this->transitions()[$from->value] ?? [], true)) {
            throw new InvalidOrderStateTransitionException($order->id, $from->value, $to->value);
        }

        foreach ($attributes as $key => $value) {
            $order->{$key} = $value;
        }

        $order->status = $to;
        $order->save();

        OrderStateTransition::query()->create([
            'order_id' => $order->id,
            'from_state' => $from->value,
            'to_state' => $to->value,
            'reason_code' => $reasonCode,
            'actor_user_id' => $actorUserId,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);

        $this->audit->record(
            actorId: $actorUserId,
            actorRole: 'order_participant',
            action: 'order.state_transition',
            targetType: 'order',
            targetId: (int) $order->id,
            before: $before,
            after: [
                'status' => $to->value,
                'fulfillment_state' => $order->fulfillment_state,
            ],
            reasonCode: $reasonCode,
            correlationId: $correlationId,
        );

        if ($from !== $to) {
            $this->notifyParticipants($order, $from, $to, $reasonCode, $actorUserId);
        }
    }

    private function notifyParticipants(Order $order, OrderStatus $from, OrderStatus $to, string $reasonCode, ?int $actorUserId): void
    {
        $orderNumber = (string) ($order->order_number ?? 'ORD-'.$order->id);
        $fromLabel = $this->statusLabel($from->value);
        $toLabel = $this->statusLabel($to->value);
        $title = 'Order status changed';
        $body = "Order {$orderNumber} changed from {$fromLabel} to {$toLabel}.";

        $participants = [
            [(int) $order->buyer_user_id, 'buyer', '/buyer/orders/'.(int) $order->id],
            [(int) ($order->seller_user_id ?? 0), 'seller', '/seller/orders/'.(int) $order->id],
        ];

        foreach ($participants as [$userId, $role, $href]) {
            if ($userId <= 0) {
                continue;
            }

            $this->notifications->notify(
                $userId,
                'order.status.changed',
                $title,
                $body,
                [
                    'role' => $role,
                    'recipient_context' => $role,
                    'recipient_role' => $role,
                    'order_id' => (int) $order->id,
                    'context_entity_type' => 'order',
                    'context_entity_id' => (int) $order->id,
                    'context_route_name' => $role.'.orders.show',
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                    'reason_code' => $reasonCode,
                    'changed_by_user_id' => $actorUserId,
                    'href' => $href,
                    'action_url' => $href,
                    'icon' => 'receipt-text',
                    'color' => 'indigo',
                ],
            );
        }
    }

    private function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}

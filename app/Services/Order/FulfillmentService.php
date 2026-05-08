<?php

namespace App\Services\Order;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\ProductType;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Models\Order;
use App\Services\Delivery\DeliveryChatService;
use App\Services\TimeoutAutomation\OrderTimeoutSnapshotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FulfillmentService
{
    public function __construct(
        private readonly OrderStateMachine $states = new OrderStateMachine(),
        private readonly DeliveryChatService $deliveryChat = new DeliveryChatService(),
        private readonly OrderTimeoutSnapshotService $timeoutSnapshots = new OrderTimeoutSnapshotService(),
    ) {
    }

    public function start(Order $order, int $actorUserId, ?string $correlationId = null): void
    {
        $type = ProductType::normalize($order->product_type);
        $this->states->transition($order, OrderStatus::Processing, match ($type) {
            ProductType::Physical => 'physical_shipment_preparation_started',
            ProductType::Digital => 'digital_delivery_thread_opened',
            ProductType::InstantDelivery => 'instant_delivery_prepared',
            ProductType::Service => 'service_work_started',
        }, $actorUserId, $correlationId, ['fulfillment_state' => 'in_progress']);

        if ($type->requiresDeliveryChat()) {
            $this->deliveryChat->addMarker($order->fresh(), $actorUserId, 'fulfillment_started', null, $type->value);
        }
    }

    public function submitDelivery(Order $order, int $actorUserId, ?string $note = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($order, $actorUserId, $note, $correlationId): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $type = ProductType::normalize($locked->product_type);
            if ($type === ProductType::Physical) {
                throw new OrderValidationFailedException($locked->id, 'physical_orders_require_shipping_details');
            }

            if (! in_array($locked->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Processing], true)) {
                throw new OrderValidationFailedException($locked->id, 'delivery_submission_not_allowed_from_state', [
                    'status' => $locked->status->value,
                ]);
            }

            if ($locked->status !== OrderStatus::Processing) {
                $this->states->transition($locked, OrderStatus::Processing, 'fulfillment_started_before_delivery_submission', $actorUserId, $correlationId);
                $locked->refresh();
            }

            $marker = $type === ProductType::InstantDelivery ? 'instant_delivery_logged' : ($type === ProductType::Service ? 'service_completed' : 'delivery_submitted');
            $this->deliveryChat->addMarker($locked, $actorUserId, $marker, $note, $type->value);

            $this->states->transition($locked, OrderStatus::DeliverySubmitted, 'seller_submitted_delivery_proof', $actorUserId, $correlationId ?? (string) Str::uuid(), [
                'fulfillment_state' => 'delivery_submitted',
                'delivery_submitted_at' => now(),
                'buyer_review_started_at' => now(),
            ]);
            $this->timeoutSnapshots->snapshotAtDeliverySubmitted($locked->fresh(), $locked->primaryProduct);
            $locked->refresh();
            $this->states->transition($locked, OrderStatus::BuyerReview, 'buyer_review_opened', $actorUserId, $correlationId ?? (string) Str::uuid(), [
                'fulfillment_state' => 'buyer_review',
            ]);
        });
    }
}

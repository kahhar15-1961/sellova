<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Domain\Enums\ProductType;
use App\Domain\Enums\EscrowEventType;
use App\Domain\Queries\Orders\OrderListQuery;
use App\Http\AppServices;
use App\Http\Requests\V1\CorrelationIdOptionalRequest;
use App\Http\Requests\V1\AddOrderShippingDetailsRequest;
use App\Http\Requests\V1\CancelOrderRequest;
use App\Http\Requests\V1\CreateOrderRequest;
use App\Http\Requests\V1\MarkOrderPaidRequest;
use App\Http\Requests\V1\MarkOrderPendingPaymentRequest;
use App\Http\Requests\V1\SubmitOrderManualPaymentRequest;
use App\Http\Requests\V1\UpdateSellerOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiEnvelope;
use App\Http\Support\AggregateHttpLookup;
use App\Http\Support\RequestPagination;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class OrderController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        return ApiEnvelope::data(OrderResource::detail($order));
    }

    public function markPendingPayment(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderMarkPendingPayment, $actor, $order);

        $command = MarkOrderPendingPaymentRequest::toCommand($request, $orderId, $actor);
        $result = $this->app->orderService()->markPendingPayment($command);

        return ApiEnvelope::data($result);
    }

    public function markPaid(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderMarkPaid, $actor, $order);

        $command = MarkOrderPaidRequest::toCommand($request, $orderId, $actor);
        $result = $this->app->orderService()->markPaid($command);

        return ApiEnvelope::data($result);
    }

    public function payWallet(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderMarkPaid, $actor, $order);

        $payload = CorrelationIdOptionalRequest::payload($request);
        $result = $this->app->orderService()->payOrderWithWallet(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        );

        return ApiEnvelope::data($result);
    }

    public function payManual(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderMarkPaid, $actor, $order);

        $payload = SubmitOrderManualPaymentRequest::payload($request);
        $result = $this->app->orderService()->payOrderWithManualMethod(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            provider: (string) $payload['provider'],
            providerReference: (string) $payload['provider_reference'],
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        );

        return ApiEnvelope::data($result);
    }

    public function store(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $command = CreateOrderRequest::toCommand($request, $actor);
        $result = $this->app->orderService()->createOrder($command);

        return ApiEnvelope::data($result);
    }

    public function advanceFulfillment(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);
        $payload = CorrelationIdOptionalRequest::payload($request);

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $command = new \App\Domain\Commands\Order\AdvanceOrderFulfillmentCommand(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        );
        $result = $this->app->orderService()->advanceFulfillment($command);

        return ApiEnvelope::data($result);
    }

    public function complete(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);
        $payload = CorrelationIdOptionalRequest::payload($request);

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $command = new \App\Domain\Commands\Order\CompleteOrderCommand(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        );
        $result = $this->app->orderService()->completeOrder($command);

        return ApiEnvelope::data($result);
    }

    public function cancel(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);
        $payload = CancelOrderRequest::payload($request);

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $command = new \App\Domain\Commands\Order\CancelOrderCommand(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            reason: $payload['reason'] ?? null,
            correlationId: $payload['correlation_id'] ?? null,
        );
        $result = $this->app->orderService()->cancelOrder($command);

        return ApiEnvelope::data($result);
    }

    public function index(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $p = RequestPagination::pageAndPerPage($request);
        $result = $this->app->orderService()->listOrders(new OrderListQuery(
            viewerUserId: (int) $actor->id,
            viewerIsPlatformStaff: $actor->isPlatformStaff(),
            page: $p['page'],
            perPage: $p['per_page'],
        ));

        return ApiEnvelope::paginated($result['items'], $result['page'], $result['per_page'], $result['total']);
    }

    public function sellerIndex(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $p = RequestPagination::pageAndPerPage($request);
        $result = $this->app->orderService()->listSellerOrders(
            viewerUserId: (int) $actor->id,
            page: $p['page'],
            perPage: $p['per_page'],
        );

        return ApiEnvelope::paginated($result['items'], $result['page'], $result['per_page'], $result['total']);
    }

    public function sellerStatus(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $payload = UpdateSellerOrderStatusRequest::payload($request);
        if ($payload['status'] !== 'processing') {
            throw new \App\Domain\Exceptions\OrderValidationFailedException($orderId, 'seller_status_not_supported', [
                'status' => $payload['status'],
            ]);
        }

        $result = $this->app->orderService()->advanceFulfillment(new \App\Domain\Commands\Order\AdvanceOrderFulfillmentCommand(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            correlationId: $payload['correlation_id'] ?? null,
        ));

        return ApiEnvelope::data($result);
    }

    public function sellerShipping(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $command = AddOrderShippingDetailsRequest::toCommand($request, $orderId, $actor);
        $result = $this->app->orderService()->addShippingDetails($command);

        return ApiEnvelope::data($result);
    }

    public function sellerSubmitDelivery(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $payload = CorrelationIdOptionalRequest::payload($request);
        $result = $this->app->orderService()->submitDelivery(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            note: isset($payload['note']) ? (string) $payload['note'] : null,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        );

        return ApiEnvelope::data($result);
    }

    public function tracking(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $order->loadMissing([
            'orderStateTransitions' => fn ($q) => $q->orderBy('created_at'),
            'escrowAccount.escrowEvents' => fn ($q) => $q->orderBy('created_at'),
        ]);
        $transitions = $order->orderStateTransitions;
        $releaseAt = $order->escrowAccount?->escrowEvents
            ?->firstWhere('event_type', EscrowEventType::Release)
            ?->created_at
            ?->toIso8601String();

        $stepAt = static function (string $toState) use ($transitions): ?string {
            foreach ($transitions as $t) {
                if ((string) $t->to_state === $toState) {
                    return $t->created_at?->toIso8601String();
                }
            }
            return null;
        };

        $productType = ProductType::normalize((string) $order->product_type);
        $proofDelivery = $productType->requiresDeliveryChat();
        $carrierName = $proofDelivery ? 'Escrow delivery chat' : 'Sellova Logistics';
        $trackingId = $proofDelivery ? '' : (string) ($order->tracking_id ?: 'TRK-'.$order->id.'-'.substr((string) ($order->uuid ?? '0000'), 0, 6));
        $trackingUrl = $proofDelivery ? '' : (string) ($order->tracking_url ?: 'https://tracking.sellova.com/'.$trackingId);
        if ($order->courier_company !== null && trim((string) $order->courier_company) !== '') {
            $carrierName = (string) $order->courier_company;
        }
        $eta = $proofDelivery ? match ($order->status->value) {
            'pending_payment', 'draft' => null,
            'paid_in_escrow', 'escrow_funded', 'processing' => $order->seller_deadline_at?->toIso8601String(),
            'delivery_submitted', 'buyer_review' => $order->buyer_review_expires_at?->toIso8601String(),
            'completed' => $order->completed_at?->toIso8601String(),
            default => null,
        } : match ($order->status->value) {
            'pending_payment', 'draft' => null,
            'paid_in_escrow', 'escrow_funded', 'processing' => $order->shipped_at?->copy()->addDays(3)->toIso8601String() ?? now()->addDays(3)->toIso8601String(),
            'delivery_submitted', 'buyer_review', 'shipped_or_delivered' => $order->delivery_submitted_at?->copy()->addDay()->toIso8601String() ?? $order->shipped_at?->copy()->addDay()->toIso8601String() ?? now()->addDay()->toIso8601String(),
            'completed' => $order->delivered_at?->toIso8601String(),
            default => null,
        };

        $timeline = $proofDelivery ? [
            ['code' => 'escrow_funded', 'title' => 'Escrow Funded', 'at' => $stepAt('escrow_funded') ?? $stepAt('paid_in_escrow') ?? $order->placed_at?->toIso8601String()],
            ['code' => 'processing', 'title' => 'Seller Preparing Delivery', 'at' => $stepAt('processing')],
            ['code' => 'delivery_submitted', 'title' => 'Proof Submitted', 'at' => $order->delivery_submitted_at?->toIso8601String() ?? $stepAt('delivery_submitted') ?? $stepAt('buyer_review')],
            ['code' => 'buyer_review', 'title' => 'Buyer Review Timer', 'at' => $order->buyer_review_started_at?->toIso8601String() ?? $stepAt('buyer_review')],
            ['code' => 'completed', 'title' => 'Escrow Released', 'at' => $stepAt('completed') ?? $order->completed_at?->toIso8601String() ?? $releaseAt],
        ] : [
            ['code' => 'escrow_funded', 'title' => 'Escrow Funded', 'at' => $stepAt('escrow_funded') ?? $stepAt('paid_in_escrow') ?? $order->placed_at?->toIso8601String()],
            ['code' => 'processing', 'title' => 'Seller Processing', 'at' => $stepAt('processing')],
            ['code' => 'delivery_submitted', 'title' => 'Delivery Submitted', 'at' => $order->delivery_submitted_at?->toIso8601String() ?? $order->shipped_at?->toIso8601String() ?? $stepAt('delivery_submitted') ?? $stepAt('buyer_review')],
            ['code' => 'buyer_review', 'title' => 'Buyer Review', 'at' => $order->buyer_review_started_at?->toIso8601String() ?? $stepAt('buyer_review')],
            ['code' => 'completed', 'title' => 'Completed', 'at' => $stepAt('completed') ?? $order->completed_at?->toIso8601String() ?? $releaseAt],
        ];

        return ApiEnvelope::data([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'carrier_name' => $carrierName,
            'tracking_id' => $trackingId,
            'tracking_url' => $trackingUrl,
            'eta' => $eta,
            'timeline' => $timeline,
            'proof_of_delivery' => [
                'delivered' => in_array($order->status->value, ['completed'], true) || $releaseAt !== null,
                'note' => in_array($order->status->value, ['completed'], true) || $releaseAt !== null
                    ? 'Delivery confirmed by buyer and escrow released.'
                    : null,
                'image_url' => null,
                'signed_by' => null,
            ],
        ]);
    }
}

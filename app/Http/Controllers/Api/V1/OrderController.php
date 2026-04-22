<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Http\Application;
use App\Http\Requests\V1\CorrelationIdOptionalRequest;
use App\Http\Requests\V1\CreateOrderRequest;
use App\Http\Requests\V1\MarkOrderPaidRequest;
use App\Http\Requests\V1\MarkOrderPendingPaymentRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiEnvelope;
use App\Models\Order;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class OrderController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = Order::query()->find($orderId);
        if ($order === null) {
            return ApiEnvelope::error('not_found', 'Order not found.', Response::HTTP_NOT_FOUND);
        }

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        return ApiEnvelope::data(OrderResource::detail($order));
    }

    public function markPendingPayment(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = Order::query()->find($orderId);
        if ($order === null) {
            return ApiEnvelope::error('not_found', 'Order not found.', Response::HTTP_NOT_FOUND);
        }

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $command = MarkOrderPendingPaymentRequest::toCommand($request, $orderId, $actor);
        $result = $this->app->orderService()->markPendingPayment($command);

        return ApiEnvelope::data($result);
    }

    public function markPaid(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = Order::query()->find($orderId);
        if ($order === null) {
            return ApiEnvelope::error('not_found', 'Order not found.', Response::HTTP_NOT_FOUND);
        }

        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $command = MarkOrderPaidRequest::toCommand($request, $orderId, $actor);
        $result = $this->app->orderService()->markPaid($command);

        return ApiEnvelope::data($result);
    }

    public function store(Request $request): Response
    {
        $this->app->requireActor($request);
        CreateOrderRequest::payload($request);

        return ApiEnvelope::notImplemented('orders', 'createOrder');
    }

    public function advanceFulfillment(Request $request): Response
    {
        $this->app->requireActor($request);
        CorrelationIdOptionalRequest::payload($request);

        return ApiEnvelope::notImplemented('orders', 'advanceFulfillment');
    }

    public function complete(Request $request): Response
    {
        $this->app->requireActor($request);
        CorrelationIdOptionalRequest::payload($request);

        return ApiEnvelope::notImplemented('orders', 'completeOrder');
    }

    public function index(Request $request): Response
    {
        $this->app->requireActor($request);

        return ApiEnvelope::notImplemented('orders', 'listOrders');
    }
}

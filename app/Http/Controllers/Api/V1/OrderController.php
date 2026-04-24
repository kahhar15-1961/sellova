<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Domain\Queries\Orders\OrderListQuery;
use App\Http\AppServices;
use App\Http\Requests\V1\CorrelationIdOptionalRequest;
use App\Http\Requests\V1\CreateOrderRequest;
use App\Http\Requests\V1\MarkOrderPaidRequest;
use App\Http\Requests\V1\MarkOrderPendingPaymentRequest;
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
}

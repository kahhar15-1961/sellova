<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReturnController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function listBuyerReturns(Request $request): Response
    {
        $actor = $this->app->requireActor($request);

        return ApiEnvelope::data($this->app->returnService()->listBuyerReturns($actor));
    }

    public function createBuyerReturn(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return ApiEnvelope::data(
            $this->app->returnService()->createBuyerReturnRequest($actor, $payload),
            201
        );
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');

        return ApiEnvelope::data($this->app->returnService()->getReturnDetail($actor, $returnId));
    }

    public function listSellerReturns(Request $request): Response
    {
        $actor = $this->app->requireActor($request);

        return ApiEnvelope::data($this->app->returnService()->listSellerReturns($actor));
    }

    public function decide(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return ApiEnvelope::data($this->app->returnService()->decideSellerReturn($actor, $returnId, $payload));
    }

    public function adminQueue(Request $request): Response
    {
        $actor = $this->app->requireActor($request);

        return ApiEnvelope::data($this->app->returnService()->listAdminQueue($actor));
    }

    public function eligibility(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');

        return ApiEnvelope::data($this->app->returnService()->eligibilityForOrder($actor, $orderId));
    }

    public function escalate(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return ApiEnvelope::data($this->app->returnService()->escalate($actor, $returnId, $payload['note'] ?? null));
    }

    public function analytics(Request $request): Response
    {
        $actor = $this->app->requireActor($request);

        return ApiEnvelope::data($this->app->returnService()->adminAnalytics($actor));
    }

    public function markBuyerShipped(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return ApiEnvelope::data($this->app->returnService()->markBuyerShipped(
            $actor,
            $returnId,
            $payload['tracking_url'] ?? null,
            $payload['carrier'] ?? null,
        ));
    }

    public function markSellerReceived(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');

        return ApiEnvelope::data($this->app->returnService()->markSellerReceived($actor, $returnId));
    }

    public function submitRefund(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return ApiEnvelope::data($this->app->returnService()->submitRefund($actor, $returnId, $payload['amount'] ?? null));
    }

    public function confirmRefund(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');

        return ApiEnvelope::data($this->app->returnService()->confirmRefund($actor, $returnId));
    }

    public function failRefund(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $returnId = (int) $request->attributes->get('returnRequestId');
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return ApiEnvelope::data($this->app->returnService()->failRefund($actor, $returnId, $payload['reason'] ?? null));
    }

    public function autoEscalate(Request $request): Response
    {
        $actor = $this->app->requireActor($request);

        return ApiEnvelope::data($this->app->returnService()->autoEscalateOverdue($actor));
    }
}


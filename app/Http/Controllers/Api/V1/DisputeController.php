<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Domain\Queries\Disputes\DisputeListQuery;
use App\Http\Application;
use App\Http\Requests\V1\DisputeResolveRefundFormRequest;
use App\Http\Requests\V1\DisputeResolveReleaseFormRequest;
use App\Http\Requests\V1\DisputeResolveSplitFormRequest;
use App\Http\Requests\V1\EscalateDisputeRequest;
use App\Http\Requests\V1\MoveDisputeToReviewRequest;
use App\Http\Requests\V1\OpenDisputeRequest;
use App\Http\Requests\V1\SubmitDisputeEvidenceRequest;
use App\Http\Resources\DisputeCaseResource;
use App\Http\Responses\ApiEnvelope;
use App\Http\Support\AggregateHttpLookup;
use App\Http\Support\RequestPagination;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DisputeController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $disputeCaseId = (int) $request->attributes->get('disputeCaseId');
        $case = AggregateHttpLookup::disputeCase($disputeCaseId);

        $this->app->domainGate()->authorize(Ability::DisputeView, $actor, $case);

        return ApiEnvelope::data(DisputeCaseResource::detail($case));
    }

    public function openForOrder(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');
        $order = AggregateHttpLookup::order($orderId);

        $this->app->domainGate()->authorize(Ability::OrderOpenDispute, $actor, $order);

        $command = OpenDisputeRequest::toCommand($request, $orderId, $actor);
        $result = $this->app->disputeService()->openDispute($command);

        return ApiEnvelope::created($result);
    }

    public function submitEvidence(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $disputeCaseId = (int) $request->attributes->get('disputeCaseId');
        $case = AggregateHttpLookup::disputeCase($disputeCaseId);

        $this->app->domainGate()->authorize(Ability::DisputeSubmitEvidence, $actor, $case);

        $command = SubmitDisputeEvidenceRequest::toCommand($request, $disputeCaseId, $actor);
        $result = $this->app->disputeService()->submitEvidence($command);

        return ApiEnvelope::data($result);
    }

    public function moveToReview(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $disputeCaseId = (int) $request->attributes->get('disputeCaseId');
        $case = AggregateHttpLookup::disputeCase($disputeCaseId);

        $this->app->domainGate()->authorize(Ability::DisputeMoveToReview, $actor, $case);

        $command = MoveDisputeToReviewRequest::toCommand($request, $disputeCaseId);
        $result = $this->app->disputeService()->moveToReview($command);

        return ApiEnvelope::data($result);
    }

    public function escalate(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $disputeCaseId = (int) $request->attributes->get('disputeCaseId');
        $case = AggregateHttpLookup::disputeCase($disputeCaseId);

        $this->app->domainGate()->authorize(Ability::DisputeEscalate, $actor, $case);

        $command = EscalateDisputeRequest::toCommand($request, $disputeCaseId);
        $result = $this->app->disputeService()->escalateDispute($command);

        return ApiEnvelope::data($result);
    }

    public function resolveRefund(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $disputeCaseId = (int) $request->attributes->get('disputeCaseId');
        $case = AggregateHttpLookup::disputeCase($disputeCaseId);

        $this->app->domainGate()->authorize(Ability::DisputeResolve, $actor, $case);

        $body = DisputeResolveRefundFormRequest::validated($request);
        $result = $this->app->disputeService()->resolveDisputeRefund(
            disputeCaseId: $disputeCaseId,
            decidedByUserId: (int) $actor->id,
            currency: $body['currency'],
            reasonCode: $body['reason_code'],
            notes: $body['notes'],
            idempotencyKey: $body['idempotency_key'],
            resolutionNotes: $body['resolution_notes'],
        );

        return ApiEnvelope::data($result);
    }

    public function resolveRelease(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $disputeCaseId = (int) $request->attributes->get('disputeCaseId');
        $case = AggregateHttpLookup::disputeCase($disputeCaseId);

        $this->app->domainGate()->authorize(Ability::DisputeResolve, $actor, $case);

        $body = DisputeResolveReleaseFormRequest::validated($request);
        $result = $this->app->disputeService()->resolveDisputeRelease(
            disputeCaseId: $disputeCaseId,
            decidedByUserId: (int) $actor->id,
            currency: $body['currency'],
            reasonCode: $body['reason_code'],
            notes: $body['notes'],
            idempotencyKey: $body['idempotency_key'],
            resolutionNotes: $body['resolution_notes'],
        );

        return ApiEnvelope::data($result);
    }

    public function resolveSplit(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $disputeCaseId = (int) $request->attributes->get('disputeCaseId');
        $case = AggregateHttpLookup::disputeCase($disputeCaseId);

        $this->app->domainGate()->authorize(Ability::DisputeResolve, $actor, $case);

        $body = DisputeResolveSplitFormRequest::validated($request);
        $result = $this->app->disputeService()->resolveDisputePartialRefund(
            disputeCaseId: $disputeCaseId,
            decidedByUserId: (int) $actor->id,
            buyerRefundAmount: $body['buyer_refund_amount'],
            currency: $body['currency'],
            reasonCode: $body['reason_code'],
            notes: $body['notes'],
            idempotencyKey: $body['idempotency_key'],
            resolutionNotes: $body['resolution_notes'],
        );

        return ApiEnvelope::data($result);
    }

    public function index(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $p = RequestPagination::pageAndPerPage($request);
        $result = $this->app->disputeService()->listDisputeCases(new DisputeListQuery(
            viewerUserId: (int) $actor->id,
            viewerIsPlatformStaff: $actor->isPlatformStaff(),
            page: $p['page'],
            perPage: $p['per_page'],
        ));

        return ApiEnvelope::paginated($result['items'], $result['page'], $result['per_page'], $result['total']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Domain\Enums\WithdrawalReviewDecision;
use App\Domain\Queries\Withdrawals\WithdrawalListQuery;
use App\Http\AppServices;
use App\Http\Requests\V1\ApproveWithdrawalFormRequest;
use App\Http\Requests\V1\PayoutTransitionRequest;
use App\Http\Requests\V1\RejectWithdrawalFormRequest;
use App\Http\Requests\V1\ReviewWithdrawalFormRequest;
use App\Http\Requests\V1\StoreWithdrawalRequest;
use App\Http\Resources\WithdrawalRequestResource;
use App\Http\Responses\ApiEnvelope;
use App\Http\Support\AggregateHttpLookup;
use App\Http\Support\RequestPagination;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WithdrawalController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function store(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $command = StoreWithdrawalRequest::toCommand($request);

        $profile = AggregateHttpLookup::sellerProfile($command->sellerProfileId);
        $wallet = AggregateHttpLookup::wallet($command->walletId);

        $this->app->domainGate()->authorize(Ability::WithdrawalRequest, $actor, $profile, $wallet);

        $result = $this->app->withdrawalService()->requestWithdrawal($command);

        return ApiEnvelope::created($result);
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = AggregateHttpLookup::withdrawalRequest($id);

        $this->app->domainGate()->authorize(Ability::WithdrawalView, $actor, $wr);

        return ApiEnvelope::data(WithdrawalRequestResource::detail($wr));
    }

    public function approve(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = AggregateHttpLookup::withdrawalRequest($id);

        $this->app->domainGate()->authorize(Ability::WithdrawalApprove, $actor, $wr);

        $command = ApproveWithdrawalFormRequest::toCommand($request, $id, $actor);
        $result = $this->app->withdrawalService()->approveWithdrawal($command);

        return ApiEnvelope::data($result);
    }

    public function reject(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = AggregateHttpLookup::withdrawalRequest($id);

        $this->app->domainGate()->authorize(Ability::WithdrawalReject, $actor, $wr);

        $command = RejectWithdrawalFormRequest::toCommand($request, $id, $actor);
        $result = $this->app->withdrawalService()->rejectWithdrawal($command);

        return ApiEnvelope::data($result);
    }

    public function review(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = AggregateHttpLookup::withdrawalRequest($id);

        $command = ReviewWithdrawalFormRequest::toCommand($request, $id, $actor);
        if ($command->decision === WithdrawalReviewDecision::Approved) {
            $this->app->domainGate()->authorize(Ability::WithdrawalApprove, $actor, $wr);
        } else {
            $this->app->domainGate()->authorize(Ability::WithdrawalReject, $actor, $wr);
        }

        $result = $this->app->withdrawalService()->reviewWithdrawal($command);

        return ApiEnvelope::data($result);
    }

    public function submitPayout(Request $request): Response
    {
        $this->app->requireActor($request);
        PayoutTransitionRequest::payload($request);

        return ApiEnvelope::notImplemented('withdrawals', 'submitPayout');
    }

    public function confirmPayout(Request $request): Response
    {
        $this->app->requireActor($request);
        PayoutTransitionRequest::payload($request);

        return ApiEnvelope::notImplemented('withdrawals', 'confirmPayout');
    }

    public function failPayout(Request $request): Response
    {
        $this->app->requireActor($request);
        PayoutTransitionRequest::payload($request);

        return ApiEnvelope::notImplemented('withdrawals', 'failPayout');
    }

    public function index(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $p = RequestPagination::pageAndPerPage($request);
        $result = $this->app->withdrawalService()->listWithdrawalRequests(new WithdrawalListQuery(
            viewerUserId: (int) $actor->id,
            viewerIsPlatformStaff: $actor->isPlatformStaff(),
            page: $p['page'],
            perPage: $p['per_page'],
        ));

        return ApiEnvelope::paginated($result['items'], $result['page'], $result['per_page'], $result['total']);
    }
}

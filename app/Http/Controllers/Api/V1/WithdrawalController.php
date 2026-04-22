<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Domain\Enums\WithdrawalReviewDecision;
use App\Http\Application;
use App\Http\Requests\V1\ApproveWithdrawalFormRequest;
use App\Http\Requests\V1\PayoutTransitionRequest;
use App\Http\Requests\V1\RejectWithdrawalFormRequest;
use App\Http\Requests\V1\ReviewWithdrawalFormRequest;
use App\Http\Requests\V1\StoreWithdrawalRequest;
use App\Http\Resources\WithdrawalRequestResource;
use App\Http\Responses\ApiEnvelope;
use App\Models\SellerProfile;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WithdrawalController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function store(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $command = StoreWithdrawalRequest::toCommand($request);

        $profile = SellerProfile::query()->find($command->sellerProfileId);
        if ($profile === null) {
            return ApiEnvelope::error('not_found', 'Seller profile not found.', Response::HTTP_NOT_FOUND);
        }

        $wallet = Wallet::query()->find($command->walletId);
        if ($wallet === null) {
            return ApiEnvelope::error('not_found', 'Wallet not found.', Response::HTTP_NOT_FOUND);
        }

        $this->app->domainGate()->authorize(Ability::WithdrawalRequest, $actor, $profile, $wallet);

        $result = $this->app->withdrawalService()->requestWithdrawal($command);

        return ApiEnvelope::created($result);
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = WithdrawalRequest::query()->find($id);
        if ($wr === null) {
            return ApiEnvelope::error('not_found', 'Withdrawal request not found.', Response::HTTP_NOT_FOUND);
        }

        $this->app->domainGate()->authorize(Ability::WithdrawalView, $actor, $wr);

        return ApiEnvelope::data(WithdrawalRequestResource::detail($wr));
    }

    public function approve(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = WithdrawalRequest::query()->find($id);
        if ($wr === null) {
            return ApiEnvelope::error('not_found', 'Withdrawal request not found.', Response::HTTP_NOT_FOUND);
        }

        $this->app->domainGate()->authorize(Ability::WithdrawalApprove, $actor, $wr);

        $command = ApproveWithdrawalFormRequest::toCommand($request, $id, $actor);
        $result = $this->app->withdrawalService()->approveWithdrawal($command);

        return ApiEnvelope::data($result);
    }

    public function reject(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = WithdrawalRequest::query()->find($id);
        if ($wr === null) {
            return ApiEnvelope::error('not_found', 'Withdrawal request not found.', Response::HTTP_NOT_FOUND);
        }

        $this->app->domainGate()->authorize(Ability::WithdrawalReject, $actor, $wr);

        $command = RejectWithdrawalFormRequest::toCommand($request, $id, $actor);
        $result = $this->app->withdrawalService()->rejectWithdrawal($command);

        return ApiEnvelope::data($result);
    }

    public function review(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $id = (int) $request->attributes->get('withdrawalRequestId');
        $wr = WithdrawalRequest::query()->find($id);
        if ($wr === null) {
            return ApiEnvelope::error('not_found', 'Withdrawal request not found.', Response::HTTP_NOT_FOUND);
        }

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
        $this->app->requireActor($request);

        return ApiEnvelope::notImplemented('withdrawals', 'listWithdrawals');
    }
}

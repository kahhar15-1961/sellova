<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Domain\Enums\WithdrawalReviewDecision;
use App\Domain\Enums\WalletType;
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
use App\Models\SellerProfile;
use App\Models\Wallet;
use App\Services\Withdrawal\WithdrawalSettingsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

final class WithdrawalController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function store(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $this->hydrateSellerWithdrawalPayload($request, (int) $actor->id);
        $command = StoreWithdrawalRequest::toCommand($request);

        $profile = AggregateHttpLookup::sellerProfile($command->sellerProfileId);
        $wallet = AggregateHttpLookup::wallet($command->walletId);

        $this->app->domainGate()->authorize(Ability::WithdrawalRequest, $actor, $profile, $wallet);

        $result = $this->app->withdrawalService()->requestWithdrawal($command);

        return ApiEnvelope::created($result);
    }

    public function settings(Request $request): Response
    {
        $this->app->requireActor($request);
        $settings = (new WithdrawalSettingsService())->current();

        return ApiEnvelope::data([
            'minimum_withdrawal_amount' => (string) $settings->minimum_withdrawal_amount,
            'currency' => (string) $settings->currency,
        ]);
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

    private function hydrateSellerWithdrawalPayload(Request $request, int $actorUserId): void
    {
        $sellerProfileId = $request->request->get('seller_profile_id');
        if ($sellerProfileId === null || (int) $sellerProfileId <= 0) {
            $profile = SellerProfile::query()
                ->where('user_id', $actorUserId)
                ->orderByDesc('id')
                ->first();
            if ($profile !== null) {
                $request->request->set('seller_profile_id', (string) $profile->id);
            }
        }

        $currency = strtoupper((string) ($request->request->get('currency') ?: 'BDT'));
        $walletId = $request->request->get('wallet_id');
        if ($walletId === null || (int) $walletId <= 0) {
            $wallet = Wallet::query()
                ->where('user_id', $actorUserId)
                ->where('wallet_type', WalletType::Seller)
                ->where('currency', $currency)
                ->orderByDesc('id')
                ->first();
            $wallet ??= Wallet::query()
                ->where('user_id', $actorUserId)
                ->where('wallet_type', WalletType::Seller)
                ->orderByDesc('id')
                ->first();
            if ($wallet !== null) {
                $request->request->set('wallet_id', (string) $wallet->id);
                $currency = strtoupper((string) ($wallet->currency ?: $currency));
            }
        }

        if (! $request->request->has('currency')) {
            $request->request->set('currency', $currency);
        }
        if (! $request->request->has('idempotency_key')) {
            $request->request->set('idempotency_key', (string) Str::uuid());
        }
    }
}

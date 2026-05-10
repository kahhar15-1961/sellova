<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletTopUp\RequestWalletTopUpCommand;
use App\Http\Requests\V1\CreateSellerProfileRequest;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Http\AppServices;
use App\Models\PushDevice;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Requests\V1\UpdateSellerProfileRequest;
use App\Http\Requests\V1\SubmitKycRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\Wallet;
use App\Models\SellerProfile;
use App\Models\WalletTopUpRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserProfileController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->getBuyerProfile((int) $actor->id);

        return ApiEnvelope::data($data);
    }

    public function showSeller(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->findSellerProfileForUser((int) $actor->id);
        if ($data === null) {
            throw new AuthValidationFailedException('seller_profile_not_found', ['user_id' => (int) $actor->id]);
        }

        return ApiEnvelope::data($data);
    }

    public function createSeller(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $command = CreateSellerProfileRequest::toCommand($request, (int) $actor->id, $actor);
        $data = $this->app->userSellerService()->createSellerProfile($command);

        return ApiEnvelope::data($data, Response::HTTP_CREATED);
    }

    public function update(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $command = UpdateProfileRequest::toCommand($request, (int) $actor->id);
        $data = $this->app->userSellerService()->updateProfile($command);

        return ApiEnvelope::data($data);
    }

    public function updateSeller(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $command = UpdateSellerProfileRequest::toCommand($request, (int) $actor->id);
        $data = $this->app->userSellerService()->updateSellerProfile($command);

        return ApiEnvelope::data($data);
    }

    public function showSellerShippingSettings(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->getSellerShippingSettings((int) $actor->id);

        return ApiEnvelope::data($data);
    }

    public function updateSellerShippingSettings(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }
        $data = $this->app->userSellerService()->updateSellerShippingSettings((int) $actor->id, $body);

        return ApiEnvelope::data($data);
    }

    public function listSellerPayoutMethods(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $items = $this->app->userSellerService()->listSellerPayoutMethods((int) $actor->id);

        return ApiEnvelope::paginated($items, 1, max(1, count($items)), count($items));
    }

    public function upsertSellerPayoutMethod(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }
        $items = $this->app->userSellerService()->upsertSellerPayoutMethod((int) $actor->id, $body);

        return ApiEnvelope::paginated($items, 1, max(1, count($items)), count($items));
    }

    public function deleteSellerPayoutMethod(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $payoutMethodId = (int) $request->attributes->get('payoutMethodId');
        $items = $this->app->userSellerService()->deleteSellerPayoutMethod((int) $actor->id, $payoutMethodId);

        return ApiEnvelope::paginated($items, 1, max(1, count($items)), count($items));
    }

    public function listSellerNotifications(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $result = $this->app->userSellerService()->listBuyerNotifications((int) $actor->id);
        $items = $result['items'];

        return ApiEnvelope::paginated($items, 1, max(1, count($items)), count($items), [
            'unread_count' => $result['unread_count'],
        ]);
    }

    public function markAllSellerNotificationsRead(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $count = $this->app->userSellerService()->markAllBuyerNotificationsRead((int) $actor->id);

        return ApiEnvelope::data(['updated' => $count]);
    }

    public function listSellerReviews(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $items = $this->app->userSellerService()->listSellerReviews((int) $actor->id);

        return ApiEnvelope::data($items);
    }

    public function replyToSellerReview(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }

        $review = $this->app->userSellerService()->replyToSellerReview(
            (int) $actor->id,
            (int) $request->attributes->get('reviewId'),
            (string) ($body['reply'] ?? ''),
        );

        return ApiEnvelope::data($review);
    }

    public function submitSellerKyc(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }
        $sellerProfileId = (int) ($body['seller_profile_id'] ?? 0);
        if ($sellerProfileId <= 0) {
            throw new AuthValidationFailedException('validation_failed', ['seller_profile_id' => 'required']);
        }
        $profile = SellerProfile::query()->whereKey($sellerProfileId)->where('user_id', (int) $actor->id)->first();
        if ($profile === null) {
            throw new AuthValidationFailedException('seller_profile_not_found', ['seller_profile_id' => $sellerProfileId]);
        }
        $result = $this->app->userSellerService()->submitKyc(
            SubmitKycRequest::toCommand($request, (int) $profile->id)
        );

        return ApiEnvelope::data($result);
    }

    public function listPaymentMethods(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->listBuyerPaymentMethods((int) $actor->id);

        return ApiEnvelope::data($data);
    }

    public function createPaymentMethod(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }
        $created = $this->app->userSellerService()->createBuyerPaymentMethod((int) $actor->id, $body);

        return ApiEnvelope::data($created, Response::HTTP_CREATED);
    }

    public function updatePaymentMethod(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $paymentMethodId = (int) $request->attributes->get('paymentMethodId');
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }
        $updated = $this->app->userSellerService()->updateBuyerPaymentMethod((int) $actor->id, $paymentMethodId, $body);

        return ApiEnvelope::data($updated);
    }

    public function setDefaultPaymentMethod(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $paymentMethodId = (int) $request->attributes->get('paymentMethodId');
        $updated = $this->app->userSellerService()->setDefaultBuyerPaymentMethod((int) $actor->id, (int) $paymentMethodId);

        return ApiEnvelope::data($updated);
    }

    public function deletePaymentMethod(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $paymentMethodId = (int) $request->attributes->get('paymentMethodId');
        $this->app->userSellerService()->deleteBuyerPaymentMethod((int) $actor->id, (int) $paymentMethodId);

        return ApiEnvelope::data(['ok' => true]);
    }

    public function listWishlist(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->listBuyerWishlist((int) $actor->id);

        return ApiEnvelope::data($data);
    }

    public function addWishlist(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            throw new AuthValidationFailedException('validation_failed', ['product_id' => 'required']);
        }
        $productId = (int) ($body['product_id'] ?? 0);
        if ($productId <= 0) {
            throw new AuthValidationFailedException('validation_failed', ['product_id' => 'required']);
        }
        $created = $this->app->userSellerService()->addBuyerWishlistItem((int) $actor->id, $productId);

        return ApiEnvelope::data($created, Response::HTTP_CREATED);
    }

    public function removeWishlist(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $productId = (int) $request->attributes->get('productId');
        $this->app->userSellerService()->removeBuyerWishlistItem((int) $actor->id, (int) $productId);

        return ApiEnvelope::data(['ok' => true]);
    }

    public function listMyReviews(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->listBuyerReviews((int) $actor->id);

        return ApiEnvelope::data($data);
    }

    public function createMyReview(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }

        $orderId = (int) ($body['order_id'] ?? 0);
        $rating = (int) ($body['rating'] ?? 0);
        $comment = trim((string) ($body['comment'] ?? ''));
        if ($orderId <= 0 || $rating < 1 || $rating > 5) {
            throw new AuthValidationFailedException('validation_failed', [
                'order_id' => $orderId <= 0 ? 'required' : null,
                'rating' => $rating < 1 || $rating > 5 ? 'between_1_and_5' : null,
            ]);
        }

        $data = $this->app->userSellerService()->createBuyerReview(
            (int) $actor->id,
            $orderId,
            $rating,
            $comment,
        );

        return ApiEnvelope::data($data, Response::HTTP_CREATED);
    }

    public function listNotifications(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->listBuyerNotifications((int) $actor->id);

        return ApiEnvelope::data($data);
    }

    public function markNotificationRead(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $notificationId = (int) $request->attributes->get('notificationId');
        $data = $this->app->userSellerService()->markBuyerNotificationRead((int) $actor->id, $notificationId);

        return ApiEnvelope::data($data);
    }

    public function markAllNotificationsRead(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $updated = $this->app->userSellerService()->markAllBuyerNotificationsRead((int) $actor->id);

        return ApiEnvelope::data(['updated' => $updated]);
    }

    public function getNotificationPreferences(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $data = $this->app->userSellerService()->getBuyerNotificationPreferences((int) $actor->id);

        return ApiEnvelope::data($data);
    }

    public function updateNotificationPreferences(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }
        $data = $this->app->userSellerService()->updateBuyerNotificationPreferences((int) $actor->id, $body);

        return ApiEnvelope::data($data);
    }

    public function registerPushDevice(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }

        $deviceToken = trim((string) ($body['device_token'] ?? ''));
        $platform = trim((string) ($body['platform'] ?? 'unknown'));
        $deviceName = isset($body['device_name']) ? trim((string) $body['device_name']) : null;
        if ($deviceToken === '') {
            throw new AuthValidationFailedException('validation_failed', ['device_token' => 'required']);
        }

        $device = PushDevice::query()->updateOrCreate(
            ['device_token' => $deviceToken],
            [
                'user_id' => (int) $actor->id,
                'platform' => $platform !== '' ? $platform : 'unknown',
                'device_name' => $deviceName !== '' ? $deviceName : null,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

        return ApiEnvelope::data([
            'id' => $device->id,
            'device_token' => $device->device_token,
            'platform' => $device->platform,
            'is_active' => $device->is_active,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }

    public function unregisterPushDevice(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }

        $deviceToken = trim((string) ($body['device_token'] ?? ''));
        if ($deviceToken === '') {
            throw new AuthValidationFailedException('validation_failed', ['device_token' => 'required']);
        }

        PushDevice::query()
            ->where('user_id', (int) $actor->id)
            ->where('device_token', $deviceToken)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        return ApiEnvelope::data(['ok' => true]);
    }

    public function listWallets(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $wallets = $this->walletsForUser((int) $actor->id);
        if ($wallets->isEmpty()) {
            $this->app->walletLedgerService()->createWalletIfMissing(new CreateWalletIfMissingCommand(
                userId: (int) $actor->id,
                walletType: WalletType::Buyer,
                currency: 'USD',
            ));
            $wallets = $this->walletsForUser((int) $actor->id);
        }

        $items = $wallets->map(function (Wallet $wallet): array {
            $balances = $this->app->walletLedgerService()->computeWalletBalances(new \App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand($wallet->id));
            $recent = $wallet->walletLedgerEntries()
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(static function ($entry): array {
                    return [
                        'id' => $entry->id,
                        'entry_type' => $entry->entry_type->value,
                        'entry_side' => $entry->entry_side->value,
                        'amount' => (string) $entry->amount,
                        'currency' => (string) $entry->currency,
                        'description' => (string) ($entry->description ?? ''),
                        'created_at' => $entry->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();

            return [
                'id' => $wallet->id,
                'uuid' => $wallet->uuid,
                'wallet_type' => $wallet->wallet_type->value,
                'currency' => (string) $wallet->currency,
                'status' => $wallet->status->value,
                'available_balance' => (string) ($balances['available_balance'] ?? '0.0000'),
                'held_balance' => (string) ($balances['held_balance'] ?? '0.0000'),
                'total_balance' => $this->formatMoney(
                    (string) (($balances['available_balance'] ?? '0') + ($balances['held_balance'] ?? '0'))
                ),
                'top_up_allowed' => $wallet->wallet_type === WalletType::Buyer,
                'recent_top_up_requests' => $wallet->walletTopUpRequests()
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get()
                    ->map(static function (WalletTopUpRequest $request): array {
                        return [
                            'id' => $request->id,
                            'status' => $request->status->value,
                            'requested_amount' => (string) $request->requested_amount,
                            'payment_method' => (string) ($request->payment_method ?? ''),
                            'payment_reference' => (string) ($request->payment_reference ?? ''),
                            'currency' => (string) ($request->currency ?? ''),
                            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
                            'rejection_reason' => $request->rejection_reason,
                            'created_at' => $request->created_at?->toIso8601String(),
                        ];
                    })
                    ->values()
                    ->all(),
                'recent_entries' => $recent,
                'created_at' => $wallet->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return ApiEnvelope::data([
            'wallets' => $items,
        ]);
    }

    public function topUpWallet(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $walletId = (int) $request->attributes->get('walletId');
        $wallet = Wallet::query()->whereKey($walletId)->where('user_id', (int) $actor->id)->first();
        if ($wallet === null) {
            throw new AuthValidationFailedException('wallet_not_found', ['wallet_id' => $walletId]);
        }
        if ($wallet->wallet_type !== WalletType::Buyer) {
            throw new AuthValidationFailedException('wallet_top_up_not_allowed', ['wallet_id' => $walletId]);
        }

        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            $body = [];
        }
        $amount = trim((string) ($body['amount'] ?? ''));
        if ($amount === '') {
            throw new AuthValidationFailedException('validation_failed', ['amount' => 'required']);
        }

        $idempotency = trim((string) ($body['correlation_id'] ?? $body['idempotency_key'] ?? ''));
        if ($idempotency === '') {
            $idempotency = 'wallet-top-up-request:'.$walletId.':'.(string) $actor->id.':'.(string) \Illuminate\Support\Str::uuid();
        }

        $result = $this->app->walletTopUpRequestService()->requestTopUp(
            new RequestWalletTopUpCommand(
                walletId: $walletId,
                userId: (int) $actor->id,
                amount: $amount,
                paymentMethod: (string) ($body['payment_method'] ?? ''),
                paymentReference: isset($body['payment_reference']) ? trim((string) $body['payment_reference']) : null,
                idempotencyKey: $idempotency,
            ),
        );

        return ApiEnvelope::data($result, Response::HTTP_CREATED);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Wallet>
     */
    private function walletsForUser(int $userId)
    {
        return Wallet::query()
            ->where('user_id', $userId)
            ->orderBy('wallet_type')
            ->orderBy('currency')
            ->get();
    }

    private function formatMoney(string $amount): string
    {
        $n = (float) $amount;
        return number_format($n, 4, '.', '');
    }
}

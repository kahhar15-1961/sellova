<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Exceptions\AuthValidationFailedException;
use App\Http\AppServices;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Requests\V1\UpdateSellerProfileRequest;
use App\Http\Responses\ApiEnvelope;
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
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Application;
use App\Http\Requests\V1\CorrelationIdOptionalRequest;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Responses\ApiEnvelope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserProfileController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request): Response
    {
        $this->app->requireActor($request);

        return ApiEnvelope::notImplemented('user_profile', 'showProfile');
    }

    public function showSeller(Request $request): Response
    {
        $this->app->requireActor($request);

        return ApiEnvelope::notImplemented('user_profile', 'showSellerProfile');
    }

    public function update(Request $request): Response
    {
        $this->app->requireActor($request);
        UpdateProfileRequest::payload($request);

        return ApiEnvelope::notImplemented('user_profile', 'updateBuyerProfile');
    }

    public function updateSeller(Request $request): Response
    {
        $this->app->requireActor($request);
        CorrelationIdOptionalRequest::payload($request);

        return ApiEnvelope::notImplemented('user_profile', 'updateSellerProfile');
    }
}

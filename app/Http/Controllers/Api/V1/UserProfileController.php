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
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Application;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RefreshSessionRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Responses\ApiEnvelope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function register(Request $request): Response
    {
        RegisterRequest::payload($request);

        return ApiEnvelope::notImplemented('auth', 'register');
    }

    public function login(Request $request): Response
    {
        LoginRequest::credentials($request);

        return ApiEnvelope::notImplemented('auth', 'login');
    }

    public function logout(Request $request): Response
    {
        $this->app->requireActor($request);

        return ApiEnvelope::notImplemented('auth', 'logout');
    }

    public function refresh(Request $request): Response
    {
        RefreshSessionRequest::token($request);

        return ApiEnvelope::notImplemented('auth', 'refresh');
    }
}

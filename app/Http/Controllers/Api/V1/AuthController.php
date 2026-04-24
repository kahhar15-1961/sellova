<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Commands\Auth\LoginCommand;
use App\Domain\Commands\Auth\LogoutCommand;
use App\Domain\Commands\Auth\RefreshTokenCommand;
use App\Domain\Commands\Auth\RegisterBuyerCommand;
use App\Domain\Commands\Auth\RegisterSellerCommand;
use App\Domain\Exceptions\AuthValidationFailedException;
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
        $payload = RegisterRequest::payload($request);
        if ($payload['account_type'] === 'buyer') {
            $result = $this->app->authService()->registerBuyer(new RegisterBuyerCommand(
                email: $payload['email'],
                phone: $payload['phone'],
                passwordPlain: $payload['password'],
                displayName: $payload['display_name'],
                countryCode: $payload['country_code'],
                defaultCurrency: $payload['default_currency'],
            ));
        } else {
            $result = $this->app->authService()->registerSeller(new RegisterSellerCommand(
                email: $payload['email'],
                phone: $payload['phone'],
                passwordPlain: $payload['password'],
                displayName: $payload['display_name'],
                legalName: (string) $payload['legal_name'],
                countryCode: $payload['country_code'],
                defaultCurrency: $payload['default_currency'],
            ));
        }

        return ApiEnvelope::data($result, Response::HTTP_CREATED);
    }

    public function login(Request $request): Response
    {
        $creds = LoginRequest::credentials($request);
        $device = $creds['device_name'] !== '' ? $creds['device_name'] : null;
        $command = new LoginCommand(
            email: $creds['email'] !== '' ? $creds['email'] : null,
            phone: $creds['phone'] !== '' ? $creds['phone'] : null,
            passwordPlain: $creds['password'],
            deviceName: $device,
        );
        $result = $this->app->authService()->login($command);

        return ApiEnvelope::data($result);
    }

    public function logout(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $this->app->authService()->logout(new LogoutCommand((int) $actor->id));

        return ApiEnvelope::data(['ok' => true]);
    }

    public function refresh(Request $request): Response
    {
        $token = RefreshSessionRequest::token($request);
        $result = $this->app->authService()->refreshToken(new RefreshTokenCommand($token));

        return ApiEnvelope::data($result);
    }

    public function loginGoogle(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            throw new AuthValidationFailedException('invalid_social_token', ['provider' => 'google']);
        }
        $idToken = trim((string) ($body['id_token'] ?? ''));
        if ($idToken === '') {
            throw new AuthValidationFailedException('invalid_social_token', ['provider' => 'google', 'detail' => 'id_token_required']);
        }
        $result = $this->app->authService()->loginWithGoogleIdToken($idToken);

        return ApiEnvelope::data($result);
    }

    public function loginApple(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            throw new AuthValidationFailedException('invalid_social_token', ['provider' => 'apple']);
        }
        $identityToken = trim((string) ($body['identity_token'] ?? ''));
        if ($identityToken === '') {
            throw new AuthValidationFailedException('invalid_social_token', ['provider' => 'apple', 'detail' => 'identity_token_required']);
        }
        $email = isset($body['email']) ? trim((string) $body['email']) : null;
        if ($email === '') {
            $email = null;
        }
        $result = $this->app->authService()->loginWithAppleIdentityToken($identityToken, $email);

        return ApiEnvelope::data($result);
    }
}

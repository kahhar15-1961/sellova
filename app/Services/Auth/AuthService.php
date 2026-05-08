<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Domain\Commands\Auth\LoginCommand;
use App\Domain\Commands\Auth\LogoutCommand;
use App\Domain\Commands\Auth\RefreshTokenCommand;
use App\Domain\Commands\Auth\RegisterBuyerCommand;
use App\Domain\Commands\Auth\RegisterSellerCommand;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Models\User;
use App\Models\UserAuthToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    private const ACCESS_TTL_SECONDS = 3600;

    private const REFRESH_TTL_DAYS = 30;

    public function registerBuyer(RegisterBuyerCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $this->assertEmailAvailable((string) $command->email);
            if ($command->phone !== null && $command->phone !== '') {
                $this->assertPhoneAvailable($command->phone);
            }

            $user = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'email' => $command->email,
                'phone' => $command->phone,
                'display_name' => $command->displayName,
                'password_hash' => password_hash($command->passwordPlain, PASSWORD_DEFAULT),
                'status' => 'active',
                'risk_level' => 'low',
            ]);

            return $this->issueTokenResponsePayload((int) $user->id);
        });
    }

    public function registerSeller(RegisterSellerCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $this->assertEmailAvailable((string) $command->email);
            if ($command->phone !== null && $command->phone !== '') {
                $this->assertPhoneAvailable($command->phone);
            }

            $user = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'email' => $command->email,
                'phone' => $command->phone,
                'display_name' => $command->displayName,
                'password_hash' => password_hash($command->passwordPlain, PASSWORD_DEFAULT),
                'status' => 'active',
                'risk_level' => 'low',
            ]);

            \App\Models\SellerProfile::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'display_name' => $command->displayName,
                'legal_name' => $command->legalName,
                'country_code' => $command->countryCode,
                'default_currency' => $command->defaultCurrency,
                'verification_status' => 'unverified',
                'store_status' => 'active',
            ]);

            return $this->issueTokenResponsePayload((int) $user->id);
        });
    }

    public function login(LoginCommand $command): array
    {
        $user = $this->resolveUserForLogin($command);
        if ($user === null || ! password_verify($command->passwordPlain, (string) $user->password_hash)) {
            throw new AuthValidationFailedException('invalid_credentials', []);
        }

        if ($user->status !== 'active') {
            throw new AuthValidationFailedException('account_inactive', ['status' => $user->status]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->issueTokenResponsePayload((int) $user->id);
    }

    public function logout(LogoutCommand $command): void
    {
        UserAuthToken::query()
            ->where('user_id', $command->userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function refreshToken(RefreshTokenCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $hash = $this->hashToken($command->refreshToken);
            $row = UserAuthToken::query()
                ->where('token_hash', $hash)
                ->where('kind', UserAuthToken::KIND_REFRESH)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->expires_at->isPast()) {
                throw new AuthValidationFailedException('invalid_refresh_token', []);
            }

            UserAuthToken::query()
                ->where('token_family', $row->token_family)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $this->issueTokenResponsePayload((int) $row->user_id);
        });
    }

    /**
     * Sign in or register a buyer using a verified Google ID token.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, user_id: int, role_codes: list<string>}
     */
    public function loginWithGoogleIdToken(string $idToken): array
    {
        $csv = (string) (getenv('GOOGLE_OAUTH_CLIENT_IDS') ?: getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '');
        $audiences = array_values(array_filter(array_map(trim(...), explode(',', $csv)), static fn (string $s): bool => $s !== ''));
        if ($audiences === []) {
            throw new AuthValidationFailedException('social_login_not_configured', ['provider' => 'google']);
        }

        try {
            $claims = GoogleIdTokenVerifier::verify($idToken, $audiences);
        } catch (\Throwable $e) {
            throw new AuthValidationFailedException('invalid_social_token', [
                'provider' => 'google',
                'detail' => $e->getMessage(),
            ]);
        }

        if (! $claims['email_verified']) {
            throw new AuthValidationFailedException('invalid_social_token', [
                'provider' => 'google',
                'detail' => 'email_not_verified',
            ]);
        }

        $email = $claims['email'];
        $display = $claims['name'] ?? explode('@', $email)[0];

        return DB::transaction(function () use ($email, $display): array {
            $user = User::query()->where('email', $email)->first();
            if ($user !== null) {
                if ($user->status !== 'active') {
                    throw new AuthValidationFailedException('account_inactive', ['status' => $user->status]);
                }
                $user->forceFill(['last_login_at' => now()])->save();

                return $this->issueTokenResponsePayload((int) $user->id);
            }

            return $this->registerBuyer(new RegisterBuyerCommand(
                email: $email,
                phone: null,
                passwordPlain: Str::random(64),
                displayName: $display !== '' ? $display : 'Google user',
                countryCode: 'US',
                defaultCurrency: 'USD',
            ));
        });
    }

    /**
     * Sign in or register a buyer using a verified Apple identity token.
     *
     * @param  string|null  $emailFromClient  Email from {@see SignInWithApple} on first authorization (JWT may omit email later).
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, user_id: int, role_codes: list<string>}
     */
    public function loginWithAppleIdentityToken(string $identityToken, ?string $emailFromClient = null): array
    {
        $appleClientId = trim((string) (getenv('APPLE_CLIENT_ID') ?: ''));
        if ($appleClientId === '') {
            throw new AuthValidationFailedException('social_login_not_configured', ['provider' => 'apple']);
        }

        try {
            $claims = AppleIdTokenVerifier::verify($identityToken, $appleClientId);
        } catch (\Throwable $e) {
            throw new AuthValidationFailedException('invalid_social_token', [
                'provider' => 'apple',
                'detail' => $e->getMessage(),
            ]);
        }

        $sub = $claims['sub'];
        $emailFromToken = $claims['email'] ?? null;
        $emailFromClient = $emailFromClient !== null ? strtolower(trim($emailFromClient)) : null;
        if ($emailFromClient === '') {
            $emailFromClient = null;
        }

        $email = $emailFromToken ?? $emailFromClient;
        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthValidationFailedException('invalid_social_token', [
                'provider' => 'apple',
                'detail' => 'email_required',
            ]);
        }

        return DB::transaction(function () use ($sub, $email): array {
            $bySub = User::query()->where('apple_sub', $sub)->first();
            if ($bySub !== null) {
                if ($bySub->status !== 'active') {
                    throw new AuthValidationFailedException('account_inactive', ['status' => $bySub->status]);
                }
                $bySub->forceFill(['last_login_at' => now()])->save();

                return $this->issueTokenResponsePayload((int) $bySub->id);
            }

            $byEmail = User::query()->where('email', $email)->first();
            if ($byEmail !== null) {
                if ($byEmail->status !== 'active') {
                    throw new AuthValidationFailedException('account_inactive', ['status' => $byEmail->status]);
                }
                $existingApple = (string) ($byEmail->apple_sub ?? '');
                if ($existingApple !== '' && $existingApple !== $sub) {
                    throw new AuthValidationFailedException('invalid_social_token', [
                        'provider' => 'apple',
                        'detail' => 'email_linked_to_different_apple_id',
                    ]);
                }
                if ($existingApple === '') {
                    $byEmail->forceFill(['apple_sub' => $sub, 'last_login_at' => now()])->save();
                } else {
                    $byEmail->forceFill(['last_login_at' => now()])->save();
                }

                return $this->issueTokenResponsePayload((int) $byEmail->id);
            }

            $user = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'email' => $email,
                'phone' => null,
                'password_hash' => password_hash(Str::random(64), PASSWORD_DEFAULT),
                'status' => 'active',
                'risk_level' => 'low',
                'apple_sub' => $sub,
            ]);

            return $this->issueTokenResponsePayload((int) $user->id);
        });
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, user_id: int, role_codes: list<string>}
     */
    private function issueTokenResponsePayload(int $userId): array
    {
        $user = User::query()->with('roles')->find($userId);
        if ($user === null) {
            throw new AuthValidationFailedException('user_not_found', ['user_id' => $userId]);
        }

        $roleCodes = $user->roles->pluck('code')->map(static fn ($code): string => (string) $code)->values()->all();

        $family = (string) Str::uuid();
        $accessPlain = 'at_'.Str::random(48);
        $refreshPlain = 'rt_'.Str::random(48);
        $now = now();
        $format = static fn (\Illuminate\Support\Carbon $c): string => $c->format('Y-m-d H:i:s.u');

        UserAuthToken::query()->insert([
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'token_family' => $family,
                'token_hash' => $this->hashToken($accessPlain),
                'kind' => UserAuthToken::KIND_ACCESS,
                'expires_at' => $format($now->copy()->addSeconds(self::ACCESS_TTL_SECONDS)),
                'revoked_at' => null,
                'created_at' => $format($now),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'token_family' => $family,
                'token_hash' => $this->hashToken($refreshPlain),
                'kind' => UserAuthToken::KIND_REFRESH,
                'expires_at' => $format($now->copy()->addDays(self::REFRESH_TTL_DAYS)),
                'revoked_at' => null,
                'created_at' => $format($now),
            ],
        ]);

        return [
            'access_token' => $accessPlain,
            'refresh_token' => $refreshPlain,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL_SECONDS,
            'user_id' => $userId,
            'role_codes' => $roleCodes,
        ];
    }

    private function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    private function assertEmailAvailable(string $email): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw new AuthValidationFailedException('email_taken', ['email' => $email]);
        }
    }

    private function assertPhoneAvailable(string $phone): void
    {
        if (User::query()->where('phone', $phone)->exists()) {
            throw new AuthValidationFailedException('phone_taken', ['phone' => $phone]);
        }
    }

    private function resolveUserForLogin(LoginCommand $command): ?User
    {
        if ($command->email !== null && $command->email !== '') {
            return User::query()->where('email', $command->email)->first();
        }
        if ($command->phone !== null && $command->phone !== '') {
            return User::query()->where('phone', $command->phone)->first();
        }

        return null;
    }
}

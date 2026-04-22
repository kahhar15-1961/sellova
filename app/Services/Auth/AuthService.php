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
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, user_id: int}
     */
    private function issueTokenResponsePayload(int $userId): array
    {
        $family = (string) Str::uuid();
        $accessPlain = 'at_'.Str::random(48);
        $refreshPlain = 'rt_'.Str::random(48);
        $now = now();

        UserAuthToken::query()->insert([
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'token_family' => $family,
                'token_hash' => $this->hashToken($accessPlain),
                'kind' => UserAuthToken::KIND_ACCESS,
                'expires_at' => $now->copy()->addSeconds(self::ACCESS_TTL_SECONDS),
                'revoked_at' => null,
                'created_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'token_family' => $family,
                'token_hash' => $this->hashToken($refreshPlain),
                'kind' => UserAuthToken::KIND_REFRESH,
                'expires_at' => $now->copy()->addDays(self::REFRESH_TTL_DAYS),
                'revoked_at' => null,
                'created_at' => $now,
            ],
        ]);

        return [
            'access_token' => $accessPlain,
            'refresh_token' => $refreshPlain,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL_SECONDS,
            'user_id' => $userId,
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

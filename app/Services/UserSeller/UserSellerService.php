<?php

declare(strict_types=1);

namespace App\Services\UserSeller;

use App\Domain\Commands\UserSeller\CreateSellerProfileCommand;
use App\Domain\Commands\UserSeller\ReviewKycCommand;
use App\Domain\Commands\UserSeller\SubmitKycCommand;
use App\Domain\Commands\UserSeller\UpdateSellerProfileCommand;
use App\Domain\Commands\UserSeller\UpdateUserProfileCommand;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserSellerService
{
    /**
     * @return array<string, mixed>
     */
    public function getBuyerProfile(int $userId): array
    {
        $user = User::query()->with('roles')->find($userId);
        if ($user === null) {
            throw new AuthValidationFailedException('user_not_found', ['user_id' => $userId]);
        }

        $roleCodes = $user->roles->pluck('code')->map(static fn ($code): string => (string) $code)->values()->all();

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'risk_level' => $user->risk_level,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'role_codes' => $roleCodes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSellerProfileForUser(int $userId): ?array
    {
        $profile = SellerProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            return null;
        }

        return $this->sellerProfileToArray($profile);
    }

    public function updateProfile(UpdateUserProfileCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $user = User::query()->whereKey($command->userId)->lockForUpdate()->first();
            if ($user === null) {
                throw new AuthValidationFailedException('user_not_found', ['user_id' => $command->userId]);
            }

            $patch = $command->patch;
            if ($patch->email !== null) {
                if (User::query()->where('email', $patch->email)->where('id', '!=', $user->id)->exists()) {
                    throw new AuthValidationFailedException('email_taken', ['email' => $patch->email]);
                }
                $user->email = $patch->email;
            }
            if ($patch->phone !== null) {
                if (User::query()->where('phone', $patch->phone)->where('id', '!=', $user->id)->exists()) {
                    throw new AuthValidationFailedException('phone_taken', ['phone' => $patch->phone]);
                }
                $user->phone = $patch->phone;
            }
            if ($patch->passwordPlain !== null) {
                $user->password_hash = password_hash($patch->passwordPlain, PASSWORD_DEFAULT);
            }
            $user->save();

            return $this->getBuyerProfile((int) $user->id);
        });
    }

    public function updateSellerProfile(UpdateSellerProfileCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $profile = SellerProfile::query()->where('user_id', $command->ownerUserId)->lockForUpdate()->first();
            if ($profile === null) {
                throw new AuthValidationFailedException('seller_profile_not_found', ['user_id' => $command->ownerUserId]);
            }
            if ($command->displayName !== null) {
                $profile->display_name = $command->displayName;
            }
            if ($command->legalName !== null) {
                $profile->legal_name = $command->legalName;
            }
            $profile->save();

            return $this->sellerProfileToArray($profile);
        });
    }

    public function createSellerProfile(CreateSellerProfileCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function submitKyc(SubmitKycCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function reviewKyc(ReviewKycCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerProfileToArray(SellerProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'uuid' => $profile->uuid,
            'user_id' => $profile->user_id,
            'display_name' => $profile->display_name,
            'legal_name' => $profile->legal_name,
            'country_code' => $profile->country_code,
            'default_currency' => $profile->default_currency,
            'verification_status' => (string) $profile->verification_status,
            'store_status' => (string) $profile->store_status,
            'created_at' => $profile->created_at?->toIso8601String(),
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }
}

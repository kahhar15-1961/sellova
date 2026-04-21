<?php

namespace App\Policies;

use App\Auth\RoleCodes;
use App\Domain\Enums\WalletType;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;

final class WithdrawalRequestPolicy
{
    /**
     * Only the seller that owns the profile (and wallet) may request a withdrawal — matches {@see \App\Services\Withdrawal\WithdrawalService} ownership checks.
     */
    public function request(User $actor, SellerProfile $sellerProfile, Wallet $wallet): bool
    {
        if ($actor->isPlatformStaff()) {
            return false;
        }

        if ((int) $sellerProfile->user_id !== (int) $actor->id) {
            return false;
        }

        if ((int) $wallet->user_id !== (int) $actor->id) {
            return false;
        }

        return $wallet->wallet_type === WalletType::Seller;
    }

    public function approve(User $actor, WithdrawalRequest $request): bool
    {
        return $actor->hasRoleCode(RoleCodes::Admin);
    }

    public function reject(User $actor, WithdrawalRequest $request): bool
    {
        return $actor->hasRoleCode(RoleCodes::Admin);
    }
}

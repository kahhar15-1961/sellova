<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

final class WalletPolicy
{
    /**
     * Wallet holder or platform staff (support / risk review).
     */
    public function view(User $actor, Wallet $wallet): bool
    {
        if ($actor->isPlatformStaff()) {
            return true;
        }

        return (int) $wallet->user_id === (int) $actor->id;
    }
}

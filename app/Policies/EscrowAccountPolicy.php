<?php

namespace App\Policies;

use App\Auth\OrderParticipant;
use App\Models\EscrowAccount;
use App\Models\User;

final class EscrowAccountPolicy
{
    /**
     * Escrow is visible to order participants and platform staff (same boundary as the parent order).
     */
    public function view(User $actor, EscrowAccount $escrow): bool
    {
        if ($actor->isPlatformStaff()) {
            return true;
        }

        $escrow->loadMissing('order');
        $order = $escrow->order;
        if ($order === null) {
            return false;
        }

        return OrderParticipant::isParticipant($actor, $order);
    }
}

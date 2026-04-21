<?php

namespace App\Policies;

use App\Models\DisputeDecision;
use App\Models\User;

/**
 * Financial adjudication rows: not exposed to buyers/sellers by default.
 */
final class DisputeDecisionPolicy
{
    public function view(User $actor, DisputeDecision $decision): bool
    {
        return $actor->isPlatformStaff();
    }
}

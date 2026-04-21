<?php

namespace App\Domain\Commands\Membership;

use App\Domain\Value\MembershipPlanDraft;

/**
 * Input contract for {@see \App\Services\Membership\MembershipService::createPlan}.
 */
final readonly class CreateMembershipPlanCommand
{
    public function __construct(
        public MembershipPlanDraft $draft,
    ) {
    }
}

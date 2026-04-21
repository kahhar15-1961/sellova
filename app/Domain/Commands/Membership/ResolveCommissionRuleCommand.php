<?php

namespace App\Domain\Commands\Membership;

/**
 * Input contract for {@see \App\Services\Membership\MembershipService::resolveCommissionRule}.
 */
final readonly class ResolveCommissionRuleCommand
{
    public function __construct(
        public int $sellerProfileId,
        public int $categoryId,
        public ?int $membershipPlanId,
    ) {
    }
}

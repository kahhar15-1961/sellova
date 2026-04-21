<?php

namespace App\Domain\Commands\Membership;

/**
 * Input contract for {@see \App\Services\Membership\MembershipService::subscribeSeller}.
 */
final readonly class SubscribeSellerToMembershipCommand
{
    public function __construct(
        public int $sellerProfileId,
        public int $membershipPlanId,
        public string $idempotencyKey,
    ) {
    }
}

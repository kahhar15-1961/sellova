<?php

namespace App\Domain\Commands\Membership;

/**
 * Input contract for {@see \App\Services\Membership\MembershipService::renewSubscription}.
 */
final readonly class RenewMembershipSubscriptionCommand
{
    public function __construct(
        public int $membershipSubscriptionId,
        public string $idempotencyKey,
    ) {
    }
}

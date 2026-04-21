<?php

namespace App\Domain\Commands\Membership;

/**
 * Input contract for {@see \App\Services\Membership\MembershipService::cancelSubscription}.
 */
final readonly class CancelMembershipSubscriptionCommand
{
    public function __construct(
        public int $membershipSubscriptionId,
    ) {
    }
}

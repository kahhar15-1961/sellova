<?php

namespace App\Services\Membership;

use App\Domain\Commands\Membership\CancelMembershipSubscriptionCommand;
use App\Domain\Commands\Membership\CreateMembershipPlanCommand;
use App\Domain\Commands\Membership\RenewMembershipSubscriptionCommand;
use App\Domain\Commands\Membership\ResolveCommissionRuleCommand;
use App\Domain\Commands\Membership\SubscribeSellerToMembershipCommand;
use App\Services\Support\FinancialCritical;

class MembershipService
{
    use FinancialCritical;

    public function createPlan(CreateMembershipPlanCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function subscribeSeller(SubscribeSellerToMembershipCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function renewSubscription(RenewMembershipSubscriptionCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function cancelSubscription(CancelMembershipSubscriptionCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function resolveCommissionRule(ResolveCommissionRuleCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}

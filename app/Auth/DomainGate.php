<?php

namespace App\Auth;

use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Models\DisputeCase;
use App\Models\DisputeDecision;
use App\Models\EscrowAccount;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Policies\DisputeCasePolicy;
use App\Policies\DisputeDecisionPolicy;
use App\Policies\EscrowAccountPolicy;
use App\Policies\OrderPolicy;
use App\Policies\WalletPolicy;
use App\Policies\WithdrawalRequestPolicy;

/**
 * Central authorization facade (ability strings → explicit policy methods).
 *
 * This project does not ship {@code illuminate/auth}; controllers should depend on this gate,
 * not on Laravel's {@code Gate} facade, until the HTTP stack is wired.
 */
final class DomainGate
{
    public function __construct(
        private readonly OrderPolicy $orderPolicy = new OrderPolicy(),
        private readonly EscrowAccountPolicy $escrowPolicy = new EscrowAccountPolicy(),
        private readonly DisputeCasePolicy $disputeCasePolicy = new DisputeCasePolicy(),
        private readonly DisputeDecisionPolicy $disputeDecisionPolicy = new DisputeDecisionPolicy(),
        private readonly WalletPolicy $walletPolicy = new WalletPolicy(),
        private readonly WithdrawalRequestPolicy $withdrawalPolicy = new WithdrawalRequestPolicy(),
    ) {
    }

    public function authorize(string $ability, User $user, mixed ...$arguments): void
    {
        if (! $this->allows($ability, $user, ...$arguments)) {
            throw new DomainAuthorizationDeniedException($ability, (int) $user->id);
        }
    }

    public function allows(string $ability, User $user, mixed ...$arguments): bool
    {
        return match ($ability) {
            Ability::OrderView => $this->orderPolicy->view($user, $this->arg($arguments, 0, Order::class)),
            Ability::OrderMarkPendingPayment => $this->orderPolicy->markPendingPayment($user, $this->arg($arguments, 0, Order::class)),
            Ability::OrderMarkPaid => $this->orderPolicy->markPaid($user, $this->arg($arguments, 0, Order::class)),
            Ability::OrderOpenDispute => $this->orderPolicy->openDisputeAsBuyer($user, $this->arg($arguments, 0, Order::class)),
            Ability::EscrowView => $this->escrowPolicy->view($user, $this->arg($arguments, 0, EscrowAccount::class)),
            Ability::DisputeView => $this->disputeCasePolicy->view($user, $this->arg($arguments, 0, DisputeCase::class)),
            Ability::DisputeSubmitEvidence => $this->disputeCasePolicy->submitEvidence($user, $this->arg($arguments, 0, DisputeCase::class)),
            Ability::DisputeMoveToReview => $this->disputeCasePolicy->moveToReview($user, $this->arg($arguments, 0, DisputeCase::class)),
            Ability::DisputeEscalate => $this->disputeCasePolicy->escalate($user, $this->arg($arguments, 0, DisputeCase::class)),
            Ability::DisputeResolve => $this->disputeCasePolicy->resolve($user, $this->arg($arguments, 0, DisputeCase::class)),
            Ability::DisputeDecisionView => $this->disputeDecisionPolicy->view($user, $this->arg($arguments, 0, DisputeDecision::class)),
            Ability::WalletView => $this->walletPolicy->view($user, $this->arg($arguments, 0, Wallet::class)),
            Ability::WithdrawalRequest => $this->withdrawalPolicy->request(
                $user,
                $this->arg($arguments, 0, SellerProfile::class),
                $this->arg($arguments, 1, Wallet::class),
            ),
            Ability::WithdrawalView => $this->withdrawalPolicy->view($user, $this->arg($arguments, 0, WithdrawalRequest::class)),
            Ability::WithdrawalApprove => $this->withdrawalPolicy->approve($user, $this->arg($arguments, 0, WithdrawalRequest::class)),
            Ability::WithdrawalReject => $this->withdrawalPolicy->reject($user, $this->arg($arguments, 0, WithdrawalRequest::class)),
            default => false,
        };
    }

    /**
     * @template T of object
     * @param  list<mixed>  $arguments
     * @param  class-string<T>  $type
     * @return T
     */
    private function arg(array $arguments, int $index, string $type): object
    {
        $value = $arguments[$index] ?? null;
        if (! $value instanceof $type) {
            throw new \InvalidArgumentException(sprintf(
                'Ability argument %d must be instance of %s.',
                $index,
                $type,
            ));
        }

        return $value;
    }
}

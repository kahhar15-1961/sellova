<?php

namespace App\Auth;

/**
 * Stable ability identifiers for {@see DomainGate}.
 */
final class Ability
{
    public const OrderView = 'order.view';

    public const OrderOpenDispute = 'order.openDispute';

    public const EscrowView = 'escrow.view';

    public const DisputeView = 'dispute.view';

    public const DisputeSubmitEvidence = 'dispute.submitEvidence';

    public const DisputeMoveToReview = 'dispute.moveToReview';

    public const DisputeEscalate = 'dispute.escalate';

    /** Staff-only adjudication (admin or adjudicator role). */
    public const DisputeResolve = 'dispute.resolve';

    public const DisputeDecisionView = 'disputeDecision.view';

    public const WalletView = 'wallet.view';

    public const WithdrawalRequest = 'withdrawal.request';

    public const WithdrawalApprove = 'withdrawal.approve';

    public const WithdrawalReject = 'withdrawal.reject';

    private function __construct()
    {
    }
}

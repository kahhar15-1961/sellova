<?php

namespace App\Domain\Enums;

/**
 * Admin review outcome for a withdrawal request (application-layer contract; maps to status transitions).
 */
enum WithdrawalReviewDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}

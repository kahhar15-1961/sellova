<?php

namespace App\Domain\Enums;

/**
 * Withdrawal request lifecycle (matches `withdrawal_requests.status` ENUM).
 */
enum WithdrawalRequestStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case ProcessingPayout = 'processing_payout';
    case PaidOut = 'paid_out';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}

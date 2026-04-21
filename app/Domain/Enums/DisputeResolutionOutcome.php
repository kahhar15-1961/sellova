<?php

namespace App\Domain\Enums;

/**
 * Dispute adjudication outcomes (matches `dispute_cases.resolution_outcome` / `dispute_decisions.outcome` ENUMs).
 */
enum DisputeResolutionOutcome: string
{
    case BuyerWins = 'buyer_wins';
    case SellerWins = 'seller_wins';
    case SplitDecision = 'split_decision';
}

<?php

namespace App\Domain\Enums;

/**
 * Wallet hold classification (matches `wallet_holds.hold_type` ENUM).
 */
enum WalletHoldType: string
{
    case Escrow = 'escrow';
    case Withdrawal = 'withdrawal';
    case RiskReview = 'risk_review';
}

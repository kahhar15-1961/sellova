<?php

namespace App\Domain\Enums;

/**
 * High-level posting intent for {@see \App\Services\WalletLedger\WalletLedgerService::postLedgerBatch}
 * (stored on `wallet_ledger_batches.event_name` as VARCHAR; values must stay schema-compatible).
 */
enum LedgerPostingEventName: string
{
    case Deposit = 'deposit';
    case EscrowHold = 'escrow_hold';
    case Release = 'release';
    case Refund = 'refund';
    case PartialRefund = 'partial_refund';
    case Fee = 'fee';
    case WithdrawalRequest = 'withdrawal_request';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';
}

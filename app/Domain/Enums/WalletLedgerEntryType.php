<?php

namespace App\Domain\Enums;

/**
 * Ledger line event types (matches `wallet_ledger_entries.entry_type` ENUM).
 */
enum WalletLedgerEntryType: string
{
    case DepositCredit = 'deposit_credit';
    case EscrowHoldDebit = 'escrow_hold_debit';
    case EscrowReleaseCredit = 'escrow_release_credit';
    case PlatformFeeCredit = 'platform_fee_credit';
    case RefundCredit = 'refund_credit';
    case WithdrawalHoldDebit = 'withdrawal_hold_debit';
    case WithdrawalSettlementDebit = 'withdrawal_settlement_debit';
    case WithdrawalReversalCredit = 'withdrawal_reversal_credit';
    case AdjustmentCredit = 'adjustment_credit';
    case AdjustmentDebit = 'adjustment_debit';
}

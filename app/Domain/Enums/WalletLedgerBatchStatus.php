<?php

namespace App\Domain\Enums;

/**
 * Ledger batch lifecycle (matches `wallet_ledger_batches.status` ENUM).
 */
enum WalletLedgerBatchStatus: string
{
    case Proposed = 'proposed';
    case Posted = 'posted';
    case Reversed = 'reversed';
}

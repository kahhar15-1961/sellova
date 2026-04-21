<?php

namespace App\Domain\Enums;

/**
 * Ledger entry side (matches `wallet_ledger_entries.entry_side` ENUM).
 */
enum WalletLedgerEntrySide: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}

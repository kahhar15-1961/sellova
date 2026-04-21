<?php

namespace App\Domain\Policy;

use App\Domain\Enums\WalletLedgerEntryType;

/**
 * Canonical rule for negative available wallet balances.
 *
 * Default policy:
 * - Negative available balances are forbidden.
 * - Exception: `adjustment_debit` may intentionally overdraw for corrective/manual actions.
 */
final class WalletNegativeBalancePolicy
{
    /**
     * Entry types that may overdraw available balance.
     *
     * @var list<WalletLedgerEntryType>
     */
    public const ALLOWED_OVERDRAW_ENTRY_TYPES = [
        WalletLedgerEntryType::AdjustmentDebit,
    ];

    public static function allowsOverdrawForEntryType(WalletLedgerEntryType $entryType): bool
    {
        return in_array($entryType, self::ALLOWED_OVERDRAW_ENTRY_TYPES, true);
    }
}


<?php

namespace App\Domain\Value;

use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;

/**
 * One ledger entry line inside a batch for {@see \App\Services\WalletLedger\WalletLedgerService::postLedgerBatch}.
 */
final readonly class LedgerPostingLine
{
    public function __construct(
        public int $walletId,
        public WalletLedgerEntrySide $entrySide,
        public WalletLedgerEntryType $entryType,
        public string $amount,
        public string $currency,
        public string $referenceType,
        public int $referenceId,
        public ?int $counterpartyWalletId,
        public ?string $description,
    ) {
    }
}

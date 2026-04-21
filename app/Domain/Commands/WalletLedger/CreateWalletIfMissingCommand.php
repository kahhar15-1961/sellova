<?php

namespace App\Domain\Commands\WalletLedger;

use App\Domain\Enums\WalletType;

/**
 * Input contract for {@see \App\Services\WalletLedger\WalletLedgerService::createWalletIfMissing}.
 */
final readonly class CreateWalletIfMissingCommand
{
    public function __construct(
        public int $userId,
        public WalletType $walletType,
        public string $currency,
    ) {
    }
}

<?php

namespace App\Domain\Commands\WalletLedger;

/**
 * Input contract for {@see \App\Services\WalletLedger\WalletLedgerService::releaseHold}.
 */
final readonly class ReleaseWalletHoldCommand
{
    public function __construct(
        public int $walletHoldId,
    ) {
    }
}

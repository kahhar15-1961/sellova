<?php

namespace App\Domain\Commands\WalletLedger;

/**
 * Input contract for {@see \App\Services\WalletLedger\WalletLedgerService::computeWalletBalances}.
 */
final readonly class ComputeWalletBalancesCommand
{
    public function __construct(
        public int $walletId,
    ) {
    }
}

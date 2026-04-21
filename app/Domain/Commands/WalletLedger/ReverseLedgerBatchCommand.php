<?php

namespace App\Domain\Commands\WalletLedger;

/**
 * Input contract for {@see \App\Services\WalletLedger\WalletLedgerService::reverseLedgerBatch}.
 */
final readonly class ReverseLedgerBatchCommand
{
    public function __construct(
        public int $batchId,
        public string $reasonCode,
    ) {
    }
}

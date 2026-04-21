<?php

namespace App\Domain\Commands\WalletLedger;

use App\Domain\Enums\WalletHoldType;

/**
 * Input contract for {@see \App\Services\WalletLedger\WalletLedgerService::placeHold}.
 */
final readonly class PlaceWalletHoldCommand
{
    public function __construct(
        public int $walletId,
        public WalletHoldType $holdType,
        public string $referenceType,
        public int $referenceId,
        public string $amount,
    ) {
    }
}

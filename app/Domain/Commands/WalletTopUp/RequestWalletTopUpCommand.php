<?php

namespace App\Domain\Commands\WalletTopUp;

/**
 * Input contract for {@see \App\Services\WalletTopUp\WalletTopUpRequestService::requestTopUp}.
 */
final readonly class RequestWalletTopUpCommand
{
    public function __construct(
        public int $walletId,
        public int $userId,
        public string $amount,
        public string $paymentMethod,
        public ?string $paymentReference,
        public string $idempotencyKey,
    ) {
    }
}

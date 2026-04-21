<?php

namespace App\Domain\Commands\Withdrawal;

/**
 * Input contract for {@see \App\Services\Withdrawal\WithdrawalService::requestWithdrawal}.
 */
final readonly class RequestWithdrawalCommand
{
    public function __construct(
        public int $sellerProfileId,
        public int $walletId,
        public string $amount,
        public string $currency,
        public string $idempotencyKey,
    ) {
    }
}

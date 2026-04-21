<?php

namespace App\Domain\Commands\Withdrawal;

/**
 * Input contract for {@see \App\Services\Withdrawal\WithdrawalService::failPayout}.
 */
final readonly class FailPayoutCommand
{
    public function __construct(
        public int $withdrawalTransactionId,
        public string $reason,
    ) {
    }
}

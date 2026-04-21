<?php

namespace App\Domain\Commands\Withdrawal;

/**
 * Input contract for {@see \App\Services\Withdrawal\WithdrawalService::confirmPayout}.
 */
final readonly class ConfirmPayoutCommand
{
    public function __construct(
        public int $withdrawalTransactionId,
    ) {
    }
}

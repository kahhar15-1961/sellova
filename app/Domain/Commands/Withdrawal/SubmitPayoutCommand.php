<?php

namespace App\Domain\Commands\Withdrawal;

/**
 * Input contract for {@see \App\Services\Withdrawal\WithdrawalService::submitPayout}.
 */
final readonly class SubmitPayoutCommand
{
    public function __construct(
        public int $withdrawalRequestId,
    ) {
    }
}

<?php

namespace App\Domain\Commands\Withdrawal;

/**
 * Input contract for {@see \App\Services\Withdrawal\WithdrawalService::rejectWithdrawal}.
 */
final readonly class RejectWithdrawalCommand
{
    public function __construct(
        public int $withdrawalRequestId,
        public int $reviewerUserId,
        public string $idempotencyKey,
        public string $reason,
    ) {
    }
}

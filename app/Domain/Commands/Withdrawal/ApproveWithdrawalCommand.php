<?php

namespace App\Domain\Commands\Withdrawal;

/**
 * Input contract for {@see \App\Services\Withdrawal\WithdrawalService::approveWithdrawal}.
 */
final readonly class ApproveWithdrawalCommand
{
    public function __construct(
        public int $withdrawalRequestId,
        public int $reviewerUserId,
        public string $idempotencyKey,
    ) {
    }
}

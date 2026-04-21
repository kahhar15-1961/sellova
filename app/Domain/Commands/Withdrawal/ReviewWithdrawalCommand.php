<?php

namespace App\Domain\Commands\Withdrawal;

use App\Domain\Enums\WithdrawalReviewDecision;

/**
 * Input contract for {@see \App\Services\Withdrawal\WithdrawalService::reviewWithdrawal}.
 */
final readonly class ReviewWithdrawalCommand
{
    public function __construct(
        public int $withdrawalRequestId,
        public int $reviewerId,
        public WithdrawalReviewDecision $decision,
        public ?string $reason,
        public string $idempotencyKey,
    ) {
    }
}

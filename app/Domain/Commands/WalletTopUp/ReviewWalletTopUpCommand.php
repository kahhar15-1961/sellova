<?php

namespace App\Domain\Commands\WalletTopUp;

use App\Domain\Enums\WalletTopUpReviewDecision;

/**
 * Input contract for {@see \App\Services\WalletTopUp\WalletTopUpRequestService::reviewTopUp}.
 */
final readonly class ReviewWalletTopUpCommand
{
    public function __construct(
        public int $walletTopUpRequestId,
        public int $reviewerUserId,
        public WalletTopUpReviewDecision $decision,
        public ?string $reason,
        public string $idempotencyKey,
    ) {
    }
}

<?php

namespace App\Domain\Commands\Dispute;

use App\Domain\Enums\DisputeResolutionOutcome;

/**
 * Input contract for {@see \App\Services\Dispute\DisputeService::resolveDispute}.
 */
final readonly class ResolveDisputeCommand
{
    public function __construct(
        public int $disputeCaseId,
        public int $decidedByUserId,
        public DisputeResolutionOutcome $outcome,
        public string $buyerAmount,
        public string $sellerAmount,
        public string $currency,
        public string $reasonCode,
    ) {
    }
}

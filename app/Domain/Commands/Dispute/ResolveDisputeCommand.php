<?php

namespace App\Domain\Commands\Dispute;

use App\Domain\Enums\DisputeResolutionOutcome;

/**
 * Input contract for {@see \App\Services\Dispute\DisputeService::resolveDispute}.
 *
 * When {@see self::$allocateBuyerFullRemaining} or {@see self::$allocateSellerFullRemaining} is true,
 * remaining escrow is read under lock and buyer/seller amounts are derived (mutually exclusive flags).
 * When {@see self::$partialBuyerRefundAmount} is set, outcome must be split_decision and the seller portion is remaining minus buyer.
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
        public string $notes,
        public string $idempotencyKey,
        public ?string $resolutionNotes = null,
        public bool $allocateBuyerFullRemaining = false,
        public bool $allocateSellerFullRemaining = false,
        public ?string $partialBuyerRefundAmount = null,
    ) {
    }
}

<?php

namespace App\Domain\Commands\Escrow;

/**
 * Atomically settles remaining escrow while {@see EscrowState::UnderDispute} (buyer refund and/or seller release).
 *
 * Buyer and seller amounts are in the same decimal currency as the escrow; their scaled sum must equal remaining held.
 */
final readonly class SettleEscrowFromDisputeCommand
{
    public function __construct(
        public int $escrowAccountId,
        public int $disputeCaseId,
        /** Refund credited to the buyer wallet (may be "0.0000"). */
        public string $buyerRefundAmount,
        /** Release credited to the seller wallet (may be "0.0000"). */
        public string $sellerReleaseAmount,
        public string $idempotencyKey,
    ) {
    }
}

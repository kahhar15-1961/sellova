<?php

namespace App\Domain\Commands\Escrow;

/**
 * Input contract for {@see \App\Services\Escrow\EscrowService::markUnderDispute}.
 */
final readonly class MarkEscrowUnderDisputeCommand
{
    public function __construct(
        public int $escrowAccountId,
        public int $disputeCaseId,
    ) {
    }
}

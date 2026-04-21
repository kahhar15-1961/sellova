<?php

namespace App\Domain\Commands\Dispute;

/**
 * Input contract for {@see \App\Services\Dispute\DisputeService::escalateDispute}.
 */
final readonly class EscalateDisputeCommand
{
    public function __construct(
        public int $disputeCaseId,
    ) {
    }
}

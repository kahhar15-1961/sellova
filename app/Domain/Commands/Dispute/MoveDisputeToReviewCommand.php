<?php

namespace App\Domain\Commands\Dispute;

/**
 * Input contract for {@see \App\Services\Dispute\DisputeService::moveToReview}.
 */
final readonly class MoveDisputeToReviewCommand
{
    public function __construct(
        public int $disputeCaseId,
    ) {
    }
}

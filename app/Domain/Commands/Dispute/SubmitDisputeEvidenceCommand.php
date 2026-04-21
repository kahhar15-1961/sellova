<?php

namespace App\Domain\Commands\Dispute;

use App\Domain\Value\DisputeEvidenceItem;

/**
 * Input contract for {@see \App\Services\Dispute\DisputeService::submitEvidence}.
 *
 * @phpstan-type EvidenceList list<DisputeEvidenceItem>
 */
final readonly class SubmitDisputeEvidenceCommand
{
    /**
     * @param  list<DisputeEvidenceItem>  $evidence
     */
    public function __construct(
        public int $disputeCaseId,
        public int $submittedByUserId,
        public array $evidence,
    ) {
    }

    public static function fromItems(
        int $disputeCaseId,
        int $submittedByUserId,
        DisputeEvidenceItem ...$evidence,
    ): self {
        return new self($disputeCaseId, $submittedByUserId, array_values($evidence));
    }
}

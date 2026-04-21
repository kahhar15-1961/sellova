<?php

namespace App\Services\Dispute;

use App\Domain\Commands\Dispute\MoveDisputeToReviewCommand;
use App\Domain\Commands\Dispute\OpenDisputeCommand;
use App\Domain\Commands\Dispute\ResolveDisputeCommand;
use App\Domain\Commands\Dispute\SubmitDisputeEvidenceCommand;
use App\Services\Support\FinancialCritical;

class DisputeService
{
    use FinancialCritical;

    public function openDispute(OpenDisputeCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function submitEvidence(SubmitDisputeEvidenceCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function moveToReview(MoveDisputeToReviewCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function resolveDispute(ResolveDisputeCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}

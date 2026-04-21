<?php

namespace App\Services\Escrow;

use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\Escrow\MarkEscrowUnderDisputeCommand;
use App\Domain\Commands\Escrow\RefundEscrowCommand;
use App\Domain\Commands\Escrow\ReleaseEscrowCommand;
use App\Services\Support\FinancialCritical;

class EscrowService
{
    use FinancialCritical;

    public function createEscrowForOrder(CreateEscrowForOrderCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function holdEscrow(HoldEscrowCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function releaseEscrow(ReleaseEscrowCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function refundEscrow(RefundEscrowCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function markUnderDispute(MarkEscrowUnderDisputeCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}

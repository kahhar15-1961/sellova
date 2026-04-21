<?php

namespace App\Services\Withdrawal;

use App\Domain\Commands\Withdrawal\ConfirmPayoutCommand;
use App\Domain\Commands\Withdrawal\FailPayoutCommand;
use App\Domain\Commands\Withdrawal\RequestWithdrawalCommand;
use App\Domain\Commands\Withdrawal\ReviewWithdrawalCommand;
use App\Domain\Commands\Withdrawal\SubmitPayoutCommand;
use App\Services\Support\FinancialCritical;

class WithdrawalService
{
    use FinancialCritical;

    public function requestWithdrawal(RequestWithdrawalCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function reviewWithdrawal(ReviewWithdrawalCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function submitPayout(SubmitPayoutCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function confirmPayout(ConfirmPayoutCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function failPayout(FailPayoutCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}

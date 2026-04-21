<?php

namespace App\Services\Order;

use App\Domain\Commands\Order\AdvanceOrderFulfillmentCommand;
use App\Domain\Commands\Order\CompleteOrderCommand;
use App\Domain\Commands\Order\CreateOrderCommand;
use App\Domain\Commands\Order\MarkOrderPaidCommand;
use App\Domain\Commands\Order\MarkOrderPendingPaymentCommand;
use App\Services\Support\FinancialCritical;

class OrderService
{
    use FinancialCritical;

    public function createOrder(CreateOrderCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function markPendingPayment(MarkOrderPendingPaymentCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function markPaid(MarkOrderPaidCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function advanceFulfillment(AdvanceOrderFulfillmentCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function completeOrder(CompleteOrderCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}

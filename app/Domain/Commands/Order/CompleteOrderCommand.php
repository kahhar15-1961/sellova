<?php

namespace App\Domain\Commands\Order;

/**
 * Input contract for {@see \App\Services\Order\OrderService::completeOrder}.
 */
final readonly class CompleteOrderCommand
{
    public function __construct(
        public int $orderId,
    ) {
    }
}

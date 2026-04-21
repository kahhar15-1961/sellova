<?php

namespace App\Domain\Commands\Order;

/**
 * Input contract for {@see \App\Services\Order\OrderService::markPaid}.
 */
final readonly class MarkOrderPaidCommand
{
    public function __construct(
        public int $orderId,
        public int $paymentTransactionId,
    ) {
    }
}

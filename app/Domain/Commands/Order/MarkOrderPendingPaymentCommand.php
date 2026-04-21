<?php

namespace App\Domain\Commands\Order;

/**
 * Input contract for {@see \App\Services\Order\OrderService::markPendingPayment}.
 */
final readonly class MarkOrderPendingPaymentCommand
{
    public function __construct(
        public int $orderId,
        public ?string $correlationId = null,
        public ?int $actorUserId = null,
    ) {
    }
}

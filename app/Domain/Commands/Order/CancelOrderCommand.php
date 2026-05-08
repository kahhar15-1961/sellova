<?php

namespace App\Domain\Commands\Order;

/**
 * Input contract for {@see \App\Services\Order\OrderService::cancelOrder}.
 */
final readonly class CancelOrderCommand
{
    public function __construct(
        public int $orderId,
        public int $actorUserId,
        public ?string $reason = null,
        public ?string $correlationId = null,
    ) {
    }
}

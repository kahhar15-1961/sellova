<?php

namespace App\Domain\Commands\Order;

use App\Domain\Enums\OrderStatus;

/**
 * Input contract for {@see \App\Services\Order\OrderService::advanceFulfillment}.
 */
final readonly class AdvanceOrderFulfillmentCommand
{
    public function __construct(
        public int $orderId,
        public int $actorUserId,
        public ?string $correlationId = null,
    ) {
    }
}

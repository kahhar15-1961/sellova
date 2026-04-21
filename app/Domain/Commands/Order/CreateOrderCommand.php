<?php

namespace App\Domain\Commands\Order;

use App\Domain\Value\CartSnapshot;

/**
 * Input contract for {@see \App\Services\Order\OrderService::createOrder}.
 */
final readonly class CreateOrderCommand
{
    public function __construct(
        public int $buyerUserId,
        public CartSnapshot $cartSnapshot,
        public string $idempotencyKey,
    ) {
    }
}

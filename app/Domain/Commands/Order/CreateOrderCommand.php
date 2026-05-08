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
        public string $shippingMethod = 'standard',
        public bool $shippingMethodProvided = false,
        public ?string $shippingAddressId = null,
        public ?string $shippingRecipientName = null,
        public ?string $shippingAddressLine = null,
        public ?string $shippingPhone = null,
        public ?string $promoCode = null,
    ) {
    }
}

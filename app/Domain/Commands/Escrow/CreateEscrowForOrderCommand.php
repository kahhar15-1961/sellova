<?php

namespace App\Domain\Commands\Escrow;

/**
 * Input contract for {@see \App\Services\Escrow\EscrowService::createEscrowForOrder}.
 */
final readonly class CreateEscrowForOrderCommand
{
    public function __construct(
        public int $orderId,
        public string $currency,
        public string $heldAmount,
        public string $idempotencyKey,
    ) {
    }
}

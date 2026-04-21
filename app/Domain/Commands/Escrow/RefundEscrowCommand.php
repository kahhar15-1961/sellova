<?php

namespace App\Domain\Commands\Escrow;

/**
 * Input contract for {@see \App\Services\Escrow\EscrowService::refundEscrow}.
 */
final readonly class RefundEscrowCommand
{
    public function __construct(
        public int $escrowAccountId,
        public string $idempotencyKey,
    ) {
    }
}

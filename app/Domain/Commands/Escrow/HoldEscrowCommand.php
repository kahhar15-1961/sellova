<?php

namespace App\Domain\Commands\Escrow;

/**
 * Input contract for {@see \App\Services\Escrow\EscrowService::holdEscrow}.
 */
final readonly class HoldEscrowCommand
{
    public function __construct(
        public int $escrowAccountId,
        public string $idempotencyKey,
    ) {
    }
}

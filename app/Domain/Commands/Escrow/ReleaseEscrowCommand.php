<?php

namespace App\Domain\Commands\Escrow;

/**
 * Input contract for {@see \App\Services\Escrow\EscrowService::releaseEscrow}.
 */
final readonly class ReleaseEscrowCommand
{
    public function __construct(
        public int $escrowAccountId,
        public string $idempotencyKey,
    ) {
    }
}

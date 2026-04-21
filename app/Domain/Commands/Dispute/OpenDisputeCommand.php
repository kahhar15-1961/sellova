<?php

namespace App\Domain\Commands\Dispute;

/**
 * Input contract for {@see \App\Services\Dispute\DisputeService::openDispute}.
 */
final readonly class OpenDisputeCommand
{
    public function __construct(
        public int $orderId,
        public ?int $orderItemId,
        public int $openedByUserId,
        public string $reasonCode,
        public ?string $idempotencyKey = null,
        public ?string $correlationId = null,
    ) {
    }
}

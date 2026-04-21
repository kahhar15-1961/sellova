<?php

namespace App\Domain\Commands\Order;

/**
 * Moves a funded order into {@see \App\Domain\Enums\OrderStatus::Disputed} when a dispute is opened.
 */
final readonly class MarkOrderDisputedCommand
{
    public function __construct(
        public int $orderId,
        public int $actorUserId,
        public ?string $correlationId = null,
    ) {
    }
}

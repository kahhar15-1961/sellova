<?php

namespace App\Domain\Commands\Order;

use App\Domain\Enums\OrderStatus;

/**
 * Applies terminal or post-dispute order status after escrow settlement (e.g. refunded vs funds released to seller).
 */
final readonly class ApplyOrderStatusAfterDisputeResolutionCommand
{
    public function __construct(
        public int $orderId,
        public OrderStatus $targetStatus,
        public int $actorUserId,
        public string $reasonCode,
        public ?string $correlationId = null,
    ) {
    }
}

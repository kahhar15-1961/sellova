<?php

namespace App\Domain\Commands\Order;

final readonly class AddOrderShippingDetailsCommand
{
    public function __construct(
        public int $orderId,
        public int $actorUserId,
        public string $courierCompany,
        public string $trackingId,
        public ?string $trackingUrl = null,
        public ?string $shippingNote = null,
        public ?string $shippedAtIso = null,
        public ?string $correlationId = null,
    ) {
    }
}

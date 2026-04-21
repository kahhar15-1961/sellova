<?php

namespace App\Domain\Value;

/**
 * Single line in a cart snapshot at checkout (matches order line intent).
 */
final readonly class CartLineItem
{
    public function __construct(
        public int $productId,
        public ?int $productVariantId,
        public int $sellerProfileId,
        public int $quantity,
        public string $unitPrice,
        public string $currency,
    ) {
    }
}

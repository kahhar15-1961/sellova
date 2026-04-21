<?php

namespace App\Domain\Value;

/**
 * Immutable cart snapshot for {@see \App\Services\Order\OrderService::createOrder}.
 *
 * @phpstan-type CartLineItems list<CartLineItem>
 */
final readonly class CartSnapshot
{
    /**
     * @param  list<CartLineItem>  $lines
     */
    public function __construct(
        public array $lines,
    ) {
    }

    public static function fromLines(CartLineItem ...$lines): self
    {
        return new self(array_values($lines));
    }
}

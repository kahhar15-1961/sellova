<?php

namespace App\Domain\Commands\Product;

/**
 * Input contract for {@see \App\Services\Product\ProductService::publishProduct}.
 */
final readonly class PublishProductCommand
{
    public function __construct(
        public int $productId,
        public int $sellerProfileId,
    ) {
    }
}

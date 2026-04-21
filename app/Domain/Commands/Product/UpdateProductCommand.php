<?php

namespace App\Domain\Commands\Product;

use App\Domain\Value\ProductDraft;

/**
 * Input contract for {@see \App\Services\Product\ProductService::updateProduct}.
 */
final readonly class UpdateProductCommand
{
    public function __construct(
        public int $productId,
        public int $sellerProfileId,
        public ProductDraft $draft,
    ) {
    }
}

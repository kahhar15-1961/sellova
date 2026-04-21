<?php

namespace App\Domain\Commands\Product;

use App\Domain\Value\ProductDraft;

/**
 * Input contract for {@see \App\Services\Product\ProductService::createProduct}.
 */
final readonly class CreateProductCommand
{
    public function __construct(
        public int $sellerProfileId,
        public ProductDraft $draft,
    ) {
    }
}

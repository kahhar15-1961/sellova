<?php

declare(strict_types=1);

namespace App\Domain\Queries\Catalog;

/**
 * Read model for {@see \App\Services\Product\ProductService::listPublishedProducts}.
 */
final readonly class ProductCatalogListQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public ?string $search = null,
        public ?int $categoryId = null,
        public ?int $storefrontId = null,
    ) {
    }
}

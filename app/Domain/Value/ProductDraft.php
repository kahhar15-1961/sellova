<?php

namespace App\Domain\Value;

/**
 * Product create/update payload (catalog fields only).
 */
final readonly class ProductDraft
{
    public function __construct(
        public int $storefrontId,
        public int $categoryId,
        public string $productType,
        public string $title,
        public ?string $description,
        public string $basePrice,
        public string $currency,
        public ?int $stock,
        public string $status,
        public string $discountPercentage = '0',
        public ?string $discountLabel = null,
        public ?string $imageUrl = null,
        /** @var list<string> */
        public array $imageUrls = [],
        /** @var array<string, mixed> */
        public array $attributes = [],
    ) {
    }
}

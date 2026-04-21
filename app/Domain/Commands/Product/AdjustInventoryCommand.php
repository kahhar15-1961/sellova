<?php

namespace App\Domain\Commands\Product;

/**
 * Input contract for {@see \App\Services\Product\ProductService::adjustInventory}.
 */
final readonly class AdjustInventoryCommand
{
    public function __construct(
        public int $inventoryRecordId,
        public int $delta,
        public string $reasonCode,
    ) {
    }
}

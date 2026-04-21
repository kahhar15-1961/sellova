<?php

namespace App\Services\Product;

use App\Domain\Commands\Product\AdjustInventoryCommand;
use App\Domain\Commands\Product\CreateProductCommand;
use App\Domain\Commands\Product\PublishProductCommand;
use App\Domain\Commands\Product\UpdateProductCommand;

class ProductService
{
    public function createProduct(CreateProductCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function updateProduct(UpdateProductCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function publishProduct(PublishProductCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function adjustInventory(AdjustInventoryCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}

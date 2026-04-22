<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Domain\Commands\Product\AdjustInventoryCommand;
use App\Domain\Commands\Product\CreateProductCommand;
use App\Domain\Commands\Product\PublishProductCommand;
use App\Domain\Commands\Product\UpdateProductCommand;
use App\Domain\Exceptions\ProductValidationFailedException;
use App\Domain\Queries\Catalog\ProductCatalogListQuery;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductService
{
    private const PUBLISHED_STATUS = 'published';

    /**
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function listPublishedProducts(ProductCatalogListQuery $query): array
    {
        $builder = $this->publishedCatalogBase();
        $this->applyCatalogFilters($builder, $query);

        return $this->paginateCatalog($builder, $query->page, $query->perPage);
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function searchPublishedProducts(ProductCatalogListQuery $query): array
    {
        if ($query->search === null || trim($query->search) === '') {
            throw new ProductValidationFailedException(null, 'search_query_required', []);
        }

        $builder = $this->publishedCatalogBase();
        $this->applyCatalogFilters($builder, $query);
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($query->search)).'%';
        $builder->where(function (Builder $w) use ($term): void {
            $w->where('title', 'like', $term)
                ->orWhere('description', 'like', $term);
        });

        return $this->paginateCatalog($builder, $query->page, $query->perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublishedProduct(int $productId): array
    {
        $product = $this->publishedCatalogBase()->whereKey($productId)->first();
        if ($product === null) {
            throw new ProductValidationFailedException($productId, 'product_not_found', ['product_id' => $productId]);
        }

        return $this->productToDetailArray($product);
    }

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

    private function publishedCatalogBase(): Builder
    {
        return Product::query()
            ->where('status', self::PUBLISHED_STATUS)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    private function applyCatalogFilters(Builder $builder, ProductCatalogListQuery $query): void
    {
        if ($query->categoryId !== null) {
            $builder->where('category_id', $query->categoryId);
        }
        if ($query->storefrontId !== null) {
            $builder->where('storefront_id', $query->storefrontId);
        }
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    private function paginateCatalog(Builder $builder, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $total = (int) $builder->count();
        $rows = (clone $builder)->forPage($page, $perPage)->get();
        $items = [];
        foreach ($rows as $product) {
            $items[] = $this->productToListArray($product);
        }
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productToListArray(Product $p): array
    {
        return [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'title' => $p->title,
            'base_price' => (string) $p->base_price,
            'currency' => $p->currency,
            'status' => $p->status,
            'seller_profile_id' => $p->seller_profile_id,
            'storefront_id' => $p->storefront_id,
            'category_id' => $p->category_id,
            'published_at' => $p->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productToDetailArray(Product $p): array
    {
        return $this->productToListArray($p) + [
            'description' => $p->description,
            'product_type' => $p->product_type,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Domain\Commands\Product\AdjustInventoryCommand;
use App\Domain\Commands\Product\CreateProductCommand;
use App\Domain\Commands\Product\PublishProductCommand;
use App\Domain\Commands\Product\UpdateProductCommand;
use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Domain\Exceptions\ProductValidationFailedException;
use App\Domain\Queries\Catalog\ProductCatalogListQuery;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\Storefront;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $product = $this->publishedCatalogBase()->with([
            'seller_profile',
            'storefront',
            'reviews' => static function ($query): void {
                $query->latest()->with('buyer')->limit(3);
            },
        ])->whereKey($productId)->first();
        if ($product === null) {
            throw new ProductValidationFailedException($productId, 'product_not_found', ['product_id' => $productId]);
        }

        $sellerProfile = $product->seller_profile;
        $storefront = $product->storefront;
        $reviewCount = (int) $product->reviews()->where('status', 'visible')->count();
        $averageRating = $reviewCount > 0
            ? round((float) $product->reviews()->where('status', 'visible')->avg('rating'), 1)
            : null;
        $latestReviews = $product->reviews()->where('status', 'visible')->latest()->with('buyer')->limit(3)->get();

        return $this->productToDetailArray($product) + [
            'seller_summary' => $sellerProfile === null ? null : [
                'seller_profile_id' => $sellerProfile->id,
                'display_name' => $sellerProfile->display_name,
                'legal_name' => $sellerProfile->legal_name,
                'country_code' => $sellerProfile->country_code,
                'default_currency' => $sellerProfile->default_currency,
                'verification_status' => $sellerProfile->verification_status,
                'store_status' => $sellerProfile->store_status,
                'storefront_id' => $storefront?->id,
                'store_name' => $storefront?->title,
                'store_description' => $storefront?->description,
                'store_slug' => $storefront?->slug,
            ],
            'review_summary' => [
                'average_rating' => $averageRating,
                'review_count' => $reviewCount,
                'latest_reviews' => $latestReviews->map(static function ($review): array {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'buyer_name' => $review->buyer?->email ?? 'Buyer',
                        'created_at' => $review->created_at?->toIso8601String(),
                    ];
                })->values()->all(),
            ],
        ];
    }

    public function createProduct(CreateProductCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $seller = SellerProfile::query()->with(['storefront'])->whereKey($command->sellerProfileId)->lockForUpdate()->first();
            if ($seller === null) {
                throw new ProductValidationFailedException(null, 'seller_profile_not_found', [
                    'seller_profile_id' => $command->sellerProfileId,
                ]);
            }

            $storefront = $command->draft->storefrontId > 0
                ? Storefront::query()
                    ->whereKey($command->draft->storefrontId)
                    ->where('seller_profile_id', $seller->id)
                    ->lockForUpdate()
                    ->first()
                : $seller->storefront;
            if ($storefront === null && $command->draft->storefrontId > 0) {
                throw new ProductValidationFailedException(null, 'storefront_not_found', [
                    'seller_profile_id' => $seller->id,
                    'storefront_id' => $command->draft->storefrontId,
                ]);
            }
            if ($storefront === null) {
                $storefront = $this->ensureDefaultStorefront($seller);
            }

            $images = $this->normalizeImageGallery($command->draft->imageUrls, $command->draft->imageUrl);

            $product = Product::query()->create([
                'uuid' => (string) Str::uuid(),
                'seller_profile_id' => $seller->id,
                'storefront_id' => $storefront->id,
                'category_id' => $command->draft->categoryId,
                'product_type' => $this->normalizeProductType($command->draft->productType),
                'title' => $command->draft->title,
                'description' => $command->draft->description,
                'base_price' => $this->normalizePrice($command->draft->basePrice),
                'discount_percentage' => $this->normalizeDiscountPercentage($command->draft->discountPercentage),
                'discount_label' => $this->nullableString($command->draft->discountLabel, 120),
                'currency' => strtoupper($command->draft->currency),
                'image_url' => $images[0] ?? null,
                'images_json' => $images !== [] ? $images : null,
                'attributes_json' => $this->normalizeAttributes($command->draft->attributes, $command->draft->productType),
                'status' => self::PUBLISHED_STATUS,
                'published_at' => now(),
            ]);

            InventoryRecord::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'stock_on_hand' => max(0, (int) ($command->draft->stock ?? 0)),
                'stock_reserved' => 0,
                'stock_sold' => 0,
                'version' => 1,
            ]);

            return $this->productToDetailArray($product->fresh());
        });
    }

    public function updateProduct(UpdateProductCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $product = Product::query()->whereKey($command->productId)->lockForUpdate()->first();
            if ($product === null) {
                throw new ProductValidationFailedException($command->productId, 'product_not_found', ['product_id' => $command->productId]);
            }
            $this->assertProductOwner($product, $command->sellerProfileId);

            $images = $this->normalizeImageGallery($command->draft->imageUrls, $command->draft->imageUrl);

            $product->fill([
                'title' => $command->draft->title,
                'description' => $command->draft->description,
                'base_price' => $this->normalizePrice($command->draft->basePrice),
                'discount_percentage' => $this->normalizeDiscountPercentage($command->draft->discountPercentage),
                'discount_label' => $this->nullableString($command->draft->discountLabel, 120),
                'currency' => strtoupper($command->draft->currency),
                'category_id' => $command->draft->categoryId,
                'product_type' => $this->normalizeProductType($command->draft->productType),
                'storefront_id' => $command->draft->storefrontId,
                'image_url' => $images[0] ?? null,
                'images_json' => $images !== [] ? $images : null,
                'attributes_json' => $this->normalizeAttributes($command->draft->attributes, $command->draft->productType),
            ]);
            $product->save();

            if ($command->draft->stock !== null) {
                $this->syncInventoryStock($product, $command->draft->stock);
            }

            return $this->productToDetailArray($product->fresh());
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function listSellerProducts(int $sellerProfileId, int $page = 1, int $perPage = 25): array
    {
        $builder = Product::query()
            ->where('seller_profile_id', $sellerProfileId)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        return $this->paginateSellerCatalog($builder, $page, $perPage);
    }

    public function publishProduct(PublishProductCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $product = Product::query()->whereKey($command->productId)->lockForUpdate()->first();
            if ($product === null) {
                throw new ProductValidationFailedException($command->productId, 'product_not_found', ['product_id' => $command->productId]);
            }
            $this->assertProductOwner($product, $command->sellerProfileId);

            if ($product->status === self::PUBLISHED_STATUS) {
                return $this->productToDetailArray($product->fresh());
            }

            if (! in_array($product->status, ['draft', 'inactive'], true)) {
                throw new ProductValidationFailedException($product->id, 'product_cannot_be_published', [
                    'current_status' => $product->status,
                ]);
            }

            $product->status = self::PUBLISHED_STATUS;
            if ($product->published_at === null) {
                $product->published_at = now();
            }
            $product->save();

            return $this->productToDetailArray($product->fresh());
        });
    }

    public function toggleProductStatus(Product $product, int $sellerProfileId, bool $active): array
    {
        return DB::transaction(function () use ($product, $sellerProfileId, $active): array {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new ProductValidationFailedException($product->id, 'product_not_found', ['product_id' => $product->id]);
            }
            $this->assertProductOwner($locked, $sellerProfileId);

            if ($active) {
                if ($locked->status !== self::PUBLISHED_STATUS) {
                    $locked->status = self::PUBLISHED_STATUS;
                    if ($locked->published_at === null) {
                        $locked->published_at = now();
                    }
                    $locked->save();
                }
            } elseif ($locked->status !== 'inactive') {
                $locked->status = 'inactive';
                $locked->save();
            }

            return $this->productToDetailArray($locked->fresh());
        });
    }

    public function adjustInventory(AdjustInventoryCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $inventory = InventoryRecord::query()->whereKey($command->inventoryRecordId)->lockForUpdate()->first();
            if ($inventory === null) {
                throw new ProductValidationFailedException(null, 'inventory_record_not_found', [
                    'inventory_record_id' => $command->inventoryRecordId,
                ]);
            }

            $targetLabel = $inventory->product_id !== null
                ? ['product_id' => $inventory->product_id]
                : ['product_variant_id' => $inventory->product_variant_id];

            $nextOnHand = $inventory->stock_on_hand + $command->delta;
            if ($nextOnHand < 0) {
                throw new ProductValidationFailedException((int) ($inventory->product_id ?? $inventory->product_variant_id), 'inventory_negative_not_allowed', array_merge($targetLabel, [
                    'delta' => $command->delta,
                    'stock_on_hand' => $inventory->stock_on_hand,
                ]));
            }

            $inventory->stock_on_hand = $nextOnHand;
            $inventory->version = $inventory->version + 1;
            $inventory->save();

            return [
                'inventory_record_id' => $inventory->id,
                'product_id' => $inventory->product_id,
                'product_variant_id' => $inventory->product_variant_id,
                'stock_on_hand' => $inventory->stock_on_hand,
                'stock_reserved' => $inventory->stock_reserved,
                'stock_sold' => $inventory->stock_sold,
                'version' => $inventory->version,
                'reason_code' => $command->reasonCode,
            ];
        });
    }

    public function deleteProduct(Product $product, int $sellerProfileId): array
    {
        return DB::transaction(function () use ($product, $sellerProfileId): array {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new ProductValidationFailedException($product->id, 'product_not_found', ['product_id' => $product->id]);
            }
            $this->assertProductOwner($locked, $sellerProfileId);

            if ($locked->trashed()) {
                return [
                    'product_id' => $locked->id,
                    'status' => 'deleted',
                    'deleted' => true,
                ];
            }

            $locked->status = 'archived';
            $locked->save();
            $locked->delete();

            return [
                'product_id' => $locked->id,
                'status' => 'deleted',
                'deleted' => true,
            ];
        });
    }

    private function publishedCatalogBase(): Builder
    {
        return Product::query()
            ->whereIn('status', [self::PUBLISHED_STATUS, 'active'])
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
        $rows = (clone $builder)->forPage($page, $perPage)->with([
            'category:id,name',
            'seller_profile:id,user_id,display_name',
        ])->get();
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
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    private function paginateSellerCatalog(Builder $builder, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $total = (int) $builder->count();
        $rows = (clone $builder)->forPage($page, $perPage)->with(['category:id,name', 'inventoryRecords'])->get();
        $items = [];
        foreach ($rows as $product) {
            $items[] = $this->productToSellerArray($product);
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
        $attributes = is_array($p->attributes_json) ? $p->attributes_json : [];
        $reviewCount = (int) Review::query()
            ->where('product_id', (int) $p->id)
            ->where('status', 'visible')
            ->count();
        $averageRating = $reviewCount > 0
            ? round((float) Review::query()
                ->where('product_id', (int) $p->id)
                ->where('status', 'visible')
                ->avg('rating'), 1)
            : null;

        return [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'title' => $p->title,
            'base_price' => (string) $p->base_price,
            'discount_percentage' => (string) ($p->discount_percentage ?? '0'),
            'discount_label' => $p->discount_label,
            'currency' => $p->currency,
            'image_url' => $p->image_url,
            'thumbnail_url' => $p->image_url,
            'images' => $p->images_json ?: ($p->image_url ? [$p->image_url] : []),
            'attributes' => $attributes,
            'product_type' => $p->product_type,
            'is_instant_delivery' => $this->isInstantDeliveryProduct($p, $attributes),
            'brand' => $attributes['brand'] ?? null,
            'condition' => $attributes['condition'] ?? null,
            'warranty_status' => $attributes['warranty_status'] ?? null,
            'product_location' => $attributes['product_location'] ?? null,
            'tags' => $attributes['tags'] ?? [],
            'status' => $p->status,
            'seller_profile_id' => $p->seller_profile_id,
            'seller_user_id' => $p->seller_profile?->user_id,
            'storefront_id' => $p->storefront_id,
            'category_id' => $p->category_id,
            'category_name' => $p->category?->name,
            'rating' => $averageRating,
            'rating_avg' => $averageRating,
            'average_rating' => $averageRating,
            'review_count' => $reviewCount,
            'reviews_count' => $reviewCount,
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
            'stock' => $p->inventoryRecords->sum('stock_on_hand'),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productToSellerArray(Product $p): array
    {
        return $this->productToDetailArray($p) + [
            'sku' => (string) ($p->uuid ?? $p->id),
            'views' => 0,
            'sold' => (int) $p->inventoryRecords->sum('stock_sold'),
            'warehouseStocks' => [
                'Main Warehouse' => (int) $p->inventoryRecords->sum('stock_on_hand'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function isInstantDeliveryProduct(Product $product, array $attributes): bool
    {
        $type = strtolower(str_replace('-', '_', (string) $product->product_type));
        if (in_array($type, ['instant_delivery', 'instant'], true)) {
            return true;
        }

        $deliveryType = strtolower(str_replace('-', '_', (string) ($attributes['delivery_type'] ?? '')));
        $fulfillment = strtolower((string) ($attributes['fulfillment'] ?? ''));
        if (in_array($deliveryType, ['instant_delivery', 'instant'], true) || str_contains($fulfillment, 'instant')) {
            return true;
        }

        return $type === 'digital';
    }

    private function assertProductOwner(Product $product, int $sellerProfileId): void
    {
        if ((int) $product->seller_profile_id !== $sellerProfileId) {
            throw new DomainAuthorizationDeniedException('product.modify', $sellerProfileId);
        }
    }

    private function syncInventoryStock(Product $product, int $targetStock): void
    {
        $inventory = InventoryRecord::query()->where('product_id', $product->id)->orderBy('id')->lockForUpdate()->first();
        if ($inventory === null) {
            InventoryRecord::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'stock_on_hand' => max(0, $targetStock),
                'stock_reserved' => 0,
                'stock_sold' => 0,
                'version' => 1,
            ]);

            return;
        }

        $inventory->stock_on_hand = max(0, $targetStock);
        $inventory->version = $inventory->version + 1;
        $inventory->save();
    }

    private function normalizePrice(string $amount): string
    {
        $value = number_format((float) $amount, 4, '.', '');
        if ((float) $value < 0) {
            throw new ProductValidationFailedException(null, 'product_price_negative', ['base_price' => $amount]);
        }
        return $value;
    }

    private function normalizeDiscountPercentage(string $percentage): string
    {
        $value = number_format((float) $percentage, 2, '.', '');
        if ((float) $value < 0 || (float) $value > 95) {
            throw new ProductValidationFailedException(null, 'product_discount_invalid', ['discount_percentage' => $percentage]);
        }

        return $value;
    }

    private function nullableString(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * @param list<string> $images
     * @return list<string>
     */
    private function normalizeImageGallery(array $images, ?string $primary): array
    {
        $gallery = [];
        $primary = trim((string) $primary);
        if ($primary !== '') {
            $gallery[] = $primary;
        }
        foreach ($images as $image) {
            $value = trim((string) $image);
            if ($value !== '') {
                $gallery[] = $value;
            }
        }

        return array_values(array_unique($gallery));
    }

    private function ensureDefaultStorefront(SellerProfile $seller): Storefront
    {
        $title = trim((string) ($seller->display_name ?: $seller->legal_name ?: 'Seller Store'));
        $slugBase = Str::slug($title) ?: 'seller-store';
        $slug = $slugBase;
        $suffix = 2;
        while (Storefront::query()->where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.$suffix;
            $suffix += 1;
        }

        return Storefront::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => (int) $seller->id,
            'slug' => $slug,
            'title' => $title,
            'description' => $seller->legal_name,
            'policy_text' => null,
            'is_public' => true,
        ]);
    }

    private function normalizeProductType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if (! in_array($normalized, ['physical', 'digital', 'instant_delivery', 'service', 'manual_delivery'], true)) {
            throw new ProductValidationFailedException(null, 'invalid_product_type', ['product_type' => $type]);
        }

        return $normalized === 'manual_delivery' ? 'service' : $normalized;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes, string $productType): array
    {
        $type = $this->normalizeProductType($productType);
        $clean = [];
        foreach ($attributes as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            if ($key === 'tags') {
                $tags = is_array($value) ? $value : explode(',', (string) $value);
                $clean['tags'] = array_values(array_unique(array_filter(array_map(
                    static fn ($tag): string => mb_substr(trim((string) $tag), 0, 40),
                    $tags,
                ))));
                continue;
            }
            $text = mb_substr(trim((string) $value), 0, 191);
            if ($text !== '') {
                $clean[$key] = $text;
            }
        }

        if ($type === 'physical') {
            $clean['delivery_mode'] = 'seller_shipping';
            unset($clean['digital_product_kind'], $clean['access_type'], $clean['subscription_duration'], $clean['account_region'], $clean['platform'], $clean['license_type']);
        } else {
            $clean['delivery_mode'] = 'instant';
            unset($clean['product_location']);
        }

        return $clean;
    }
}

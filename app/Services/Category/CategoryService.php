<?php

declare(strict_types=1);

namespace App\Services\Category;

use App\Models\Category;
use App\Models\SellerCategoryRequest;
use App\Models\User;
use Illuminate\Support\Str;

final class CategoryService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveRootCategories(): array
    {
        return $this->listActiveCategories(rootOnly: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveCategories(bool $rootOnly = false): array
    {
        $rows = Category::query()
            ->where('is_active', true)
            ->withCount([
                'products as products_count' => static function ($q): void {
                    $q->where('status', 'published')
                        ->whereNotNull('published_at');
                },
            ])
            ->when($rootOnly, static fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => $row->id,
                'parent_id' => $row->parent_id,
                'slug' => $row->slug,
                'name' => $row->name,
                'description' => $row->description,
                'image_url' => $row->image_url,
                'sort_order' => $row->sort_order,
                'is_active' => (bool) $row->is_active,
                'products_count' => (int) ($row->products_count ?? 0),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdminCategories(): array
    {
        return Category::query()
            ->with(['parent:id,name'])
            ->withCount('products')
            ->orderByRaw('parent_id is not null')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $row): array => $this->categoryToAdminArray($row))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdminRequests(): array
    {
        return SellerCategoryRequest::query()
            ->with(['sellerProfile:id,display_name', 'parent:id,name', 'resolvedCategory:id,name'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (SellerCategoryRequest $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'status' => $row->status,
                'parent_id' => $row->parent_id,
                'reason' => $row->reason,
                'example_product_name' => $row->example_product_name,
                'seller' => $row->sellerProfile?->display_name ?? 'Seller #'.$row->seller_profile_id,
                'parent' => $row->parent?->name,
                'resolved_category' => $row->resolvedCategory?->name,
                'admin_note' => $row->admin_note,
                'created_at' => $row->created_at?->toIso8601String(),
                'reviewed_at' => $row->reviewed_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createCategory(array $payload): Category
    {
        return Category::query()->create([
            'parent_id' => $this->nullablePositiveInt($payload['parent_id'] ?? null),
            'name' => trim((string) $payload['name']),
            'slug' => $this->uniqueSlug((string) ($payload['slug'] ?? $payload['name'])),
            'description' => $this->nullableString($payload['description'] ?? null),
            'image_url' => $this->nullableString($payload['image_url'] ?? null),
            'is_active' => $this->boolValue($payload['is_active'] ?? true),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateCategory(Category $category, array $payload): Category
    {
        $category->fill([
            'parent_id' => $this->nullablePositiveInt($payload['parent_id'] ?? null),
            'name' => trim((string) ($payload['name'] ?? $category->name)),
            'description' => $this->nullableString($payload['description'] ?? null),
            'image_url' => $this->nullableString($payload['image_url'] ?? null),
            'is_active' => $this->boolValue($payload['is_active'] ?? $category->is_active),
            'sort_order' => (int) ($payload['sort_order'] ?? $category->sort_order),
        ]);
        if (array_key_exists('slug', $payload) && trim((string) $payload['slug']) !== $category->slug) {
            $category->slug = $this->uniqueSlug((string) $payload['slug'], (int) $category->id);
        }
        $category->save();

        return $category;
    }

    public function submitSellerRequest(User $actor, string $name, ?int $parentId, ?string $reason, ?string $exampleProductName): SellerCategoryRequest
    {
        $sellerProfile = $actor->sellerProfile;
        if ($sellerProfile === null) {
            throw new \RuntimeException('seller_profile_not_found');
        }

        return SellerCategoryRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $sellerProfile->id,
            'requested_by_user_id' => $actor->id,
            'parent_id' => $parentId,
            'name' => trim($name),
            'slug' => Str::slug($name),
            'reason' => $this->nullableString($reason),
            'example_product_name' => $this->nullableString($exampleProductName),
            'status' => 'pending',
        ]);
    }

    public function approveRequest(SellerCategoryRequest $request, User $reviewer, ?int $parentId, ?string $adminNote): Category
    {
        if ($request->status === 'approved' && $request->resolved_category_id) {
            return Category::query()->findOrFail($request->resolved_category_id);
        }
        $category = $this->createCategory([
            'parent_id' => $parentId ?? $request->parent_id,
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->reason,
            'is_active' => true,
        ]);
        $request->fill([
            'status' => 'approved',
            'resolved_category_id' => $category->id,
            'reviewed_by' => $reviewer->id,
            'admin_note' => $adminNote,
            'reviewed_at' => now(),
        ])->save();

        return $category;
    }

    public function rejectRequest(SellerCategoryRequest $request, User $reviewer, ?string $adminNote): void
    {
        $request->fill([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'admin_note' => $adminNote,
            'reviewed_at' => now(),
        ])->save();
    }

    private function categoryToAdminArray(Category $row): array
    {
        return [
            'id' => $row->id,
            'parent_id' => $row->parent_id,
            'parent' => $row->parent?->name,
            'name' => $row->name,
            'slug' => $row->slug,
            'description' => $row->description,
            'image_url' => $row->image_url,
            'sort_order' => $row->sort_order,
            'is_active' => (bool) $row->is_active,
            'products_count' => (int) ($row->products_count ?? 0),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    private function uniqueSlug(string $value, ?int $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, static fn ($q) => $q->whereKeyNot($exceptId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i += 1;
        }

        return $slug;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        $int = is_numeric($value) ? (int) $value : 0;
        return $int > 0 ? $int : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));
        return $string === '' ? null : $string;
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}

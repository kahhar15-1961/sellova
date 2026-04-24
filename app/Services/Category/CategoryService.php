<?php

declare(strict_types=1);

namespace App\Services\Category;

use App\Models\Category;

final class CategoryService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveRootCategories(): array
    {
        $rows = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->withCount([
                'products as products_count' => static function ($q): void {
                    $q->where('status', 'published')
                        ->whereNotNull('published_at');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => $row->id,
                'slug' => $row->slug,
                'name' => $row->name,
                'sort_order' => $row->sort_order,
                'is_active' => (bool) $row->is_active,
                'products_count' => (int) ($row->products_count ?? 0),
            ];
        }

        return $items;
    }
}


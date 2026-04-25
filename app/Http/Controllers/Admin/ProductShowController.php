<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductShowController extends AdminPageController
{
    public function __invoke(Request $request, Product $product): Response
    {
        $product->load(['seller_profile:id,display_name,user_id', 'storefront:id,title', 'category:id,name']);

        $canModerate = $request->user()?->hasPermissionCode(AdminPermission::PRODUCTS_MODERATE) ?? false;

        return Inertia::render('Admin/Products/Show', [
            'header' => $this->pageHeader(
                'Product #'.$product->id,
                'Catalog listing details with moderation controls.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Products', 'href' => route('admin.products.index')],
                    ['label' => $product->title ?? ('#'.$product->id)],
                ],
            ),
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'description' => $product->description,
                'status' => (string) $product->status,
                'price' => trim(($product->currency ?? '').' '.(string) $product->base_price),
                'currency' => $product->currency,
                'base_price' => (string) $product->base_price,
                'type' => $product->product_type,
                'published_at' => $product->published_at?->toIso8601String(),
                'updated_at' => $product->updated_at?->toIso8601String(),
                'seller' => $product->seller_profile?->display_name,
                'storefront' => $product->storefront?->title,
                'category' => $product->category?->name,
            ],
            'can_moderate' => $canModerate,
            'moderate_url' => route('admin.products.moderate', $product),
            'list_href' => route('admin.products.index'),
        ]);
    }
}

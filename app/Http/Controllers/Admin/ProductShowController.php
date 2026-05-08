<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\AdminActionApproval;
use App\Models\AuditLog;
use App\Models\DisputeCase;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Services\Promotion\PromotionService;
use App\Support\AdminReasonCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductShowController extends AdminPageController
{
    public function __construct(private readonly PromotionService $promotionService = new PromotionService())
    {
    }

    public function __invoke(Request $request, Product $product): Response
    {
        $product->load([
            'seller_profile:id,display_name,user_id,city,verification_status,store_status',
            'storefront:id,title,slug,is_public',
            'category:id,name',
            'inventoryRecords',
            'productVariants.inventoryRecords',
        ]);

        $canModerate = $request->user()?->hasPermissionCode(AdminPermission::PRODUCTS_MODERATE) ?? false;
        $attributes = is_array($product->attributes_json) ? $product->attributes_json : [];
        $images = $this->productImages($product);
        $activeCampaign = $this->promotionService->bestCatalogCampaignForProduct($product);
        $qualityChecks = [
            ['label' => 'Title present', 'ok' => $product->title !== null && trim((string) $product->title) !== ''],
            ['label' => 'Description present', 'ok' => $product->description !== null && trim((string) $product->description) !== ''],
            ['label' => 'Category assigned', 'ok' => $product->category_id !== null],
            ['label' => 'Currency set', 'ok' => $product->currency !== null && trim((string) $product->currency) !== ''],
            ['label' => 'Product image present', 'ok' => $images !== []],
            ['label' => 'Fulfillment type set', 'ok' => $product->product_type !== null && trim((string) $product->product_type) !== ''],
        ];
        $orderItemIds = OrderItem::query()->where('product_id', $product->id)->pluck('id');
        $recentOrderItems = OrderItem::query()
            ->where('product_id', $product->id)
            ->with(['order:id,order_number,status,placed_at,currency,gross_amount'])
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(static fn (OrderItem $item): array => [
                'id' => $item->id,
                'order_number' => $item->order?->order_number ?? '#'.$item->order_id,
                'order_href' => $item->order ? route('admin.orders.show', $item->order) : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => (string) $item->unit_price_snapshot,
                'line_total' => (string) $item->line_total_snapshot,
                'delivery_state' => (string) $item->delivery_state,
                'status' => $item->order?->status instanceof \BackedEnum ? $item->order->status->value : (string) ($item->order?->status ?? '—'),
                'placed_at' => $item->order?->placed_at?->toIso8601String(),
            ])
            ->values()
            ->all();
        $recentReviews = Review::query()
            ->where('product_id', $product->id)
            ->with(['buyer:id,email'])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(static fn (Review $review): array => [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'status' => (string) $review->status,
                'comment' => $review->comment,
                'buyer' => $review->buyer?->email ?? '—',
                'created_at' => $review->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
        $inventorySummary = [
            'on_hand' => (int) $product->inventoryRecords->sum('stock_on_hand'),
            'reserved' => (int) $product->inventoryRecords->sum('stock_reserved'),
            'sold' => (int) $product->inventoryRecords->sum('stock_sold'),
            'available' => max(0, (int) $product->inventoryRecords->sum('stock_on_hand') - (int) $product->inventoryRecords->sum('stock_reserved')),
        ];

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
                'discount_percentage' => (string) ($product->discount_percentage ?? '0'),
                'discount_label' => $product->discount_label,
                'active_campaign' => $activeCampaign,
                'type' => $product->product_type,
                'type_label' => $this->productTypeLabel((string) ($product->product_type ?? '')),
                'type_hint' => $this->productTypeHint((string) ($product->product_type ?? '')),
                'uuid' => $product->uuid,
                'image_url' => $this->imageUrl($product->image_url),
                'images' => $images,
                'attributes' => $attributes,
                'attribute_rows' => $this->attributeRows($attributes),
                'published_at' => $product->published_at?->toIso8601String(),
                'created_at' => $product->created_at?->toIso8601String(),
                'updated_at' => $product->updated_at?->toIso8601String(),
                'seller' => $product->seller_profile?->display_name,
                'seller_city' => $product->seller_profile?->city,
                'seller_verification_status' => $product->seller_profile?->verification_status,
                'seller_store_status' => $product->seller_profile?->store_status,
                'storefront' => $product->storefront?->title,
                'storefront_slug' => $product->storefront?->slug,
                'storefront_public' => (bool) ($product->storefront?->is_public ?? false),
                'category' => $product->category?->name,
                'image_count' => count($images),
                'attribute_count' => count($attributes),
                'public_href' => route('web.products.show', $product->id),
            ],
            'inventory_summary' => $inventorySummary,
            'inventory_records' => $product->inventoryRecords
                ->map(static fn ($record): array => [
                    'id' => $record->id,
                    'variant_id' => $record->product_variant_id,
                    'variant' => $record->product_variant_id ? '#'.$record->product_variant_id : 'Base product',
                    'on_hand' => (int) $record->stock_on_hand,
                    'reserved' => (int) $record->stock_reserved,
                    'sold' => (int) $record->stock_sold,
                    'available' => max(0, (int) $record->stock_on_hand - (int) $record->stock_reserved),
                    'updated_at' => $record->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'variants' => $product->productVariants
                ->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'title' => $variant->title,
                    'price_delta' => (string) $variant->price_delta,
                    'is_active' => (bool) $variant->is_active,
                    'attributes' => is_array($variant->attributes_json) ? $variant->attributes_json : [],
                    'stock_on_hand' => (int) $variant->inventoryRecords->sum('stock_on_hand'),
                    'stock_reserved' => (int) $variant->inventoryRecords->sum('stock_reserved'),
                    'stock_sold' => (int) $variant->inventoryRecords->sum('stock_sold'),
                    'updated_at' => $variant->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'ops_metrics' => [
                'total_order_items' => (int) OrderItem::query()->where('product_id', $product->id)->count(),
                'open_disputes' => (int) DisputeCase::query()->whereIn('order_item_id', $orderItemIds)->where('status', '!=', 'resolved')->count(),
                'avg_rating' => round((float) (Review::query()->where('product_id', $product->id)->avg('rating') ?? 0), 2),
            ],
            'recent_order_items' => $recentOrderItems,
            'recent_reviews' => $recentReviews,
            'quality_checks' => $qualityChecks,
            'moderation_reason_options' => [
                ...AdminReasonCatalog::productPolicyCodes(),
            ],
            'pending_approvals' => AdminActionApproval::query()
                ->where('target_type', 'product')
                ->where('target_id', $product->id)
                ->where('status', 'pending')
                ->with(['requested_by_user:id,email'])
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(static fn (AdminActionApproval $a): array => [
                    'id' => $a->id,
                    'action_code' => $a->action_code,
                    'reason_code' => $a->reason_code,
                    'requested_by' => $a->requested_by_user?->email ?? '—',
                    'requested_at' => $a->requested_at?->toIso8601String(),
                    'decision_url' => route('admin.action-approvals.decide', $a),
                ])->values()->all(),
            'timeline' => AuditLog::query()
                ->where('target_type', 'product')
                ->where('target_id', $product->id)
                ->with(['actor_user:id,email'])
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(static fn (AuditLog $l): array => [
                    'id' => $l->id,
                    'action' => $l->action,
                    'reason_code' => $l->reason_code,
                    'actor' => $l->actor_user?->email ?? '—',
                    'created_at' => $l->created_at?->toIso8601String(),
                ])->values()->all(),
            'can_moderate' => $canModerate,
            'moderate_url' => route('admin.products.moderate', $product),
            'list_href' => route('admin.products.index'),
        ]);
    }

    private function productTypeLabel(string $type): string
    {
        return match ($type) {
            'physical' => 'Physical',
            'digital' => 'Digital',
            'instant_delivery' => 'Instant delivery',
            'service' => 'Service',
            default => $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : '—',
        };
    }

    private function productTypeHint(string $type): string
    {
        return match ($type) {
            'physical' => 'Shipping, stock, and delivery tracking apply.',
            'digital' => 'Digital delivery is handled through proof or file handoff.',
            'instant_delivery' => 'Automatic digital fulfillment is expected.',
            'service' => 'Service delivery workflow applies after purchase.',
            default => 'Product fulfillment type is not classified.',
        };
    }

    /**
     * @return list<array{url: string, raw: string, is_primary: bool}>
     */
    private function productImages(Product $product): array
    {
        $rawImages = is_array($product->images_json) ? $product->images_json : [];
        if ($product->image_url !== null && trim((string) $product->image_url) !== '') {
            array_unshift($rawImages, (string) $product->image_url);
        }

        $seen = [];
        $images = [];
        foreach ($rawImages as $image) {
            $raw = trim((string) $image);
            if ($raw === '' || isset($seen[$raw])) {
                continue;
            }
            $seen[$raw] = true;
            $images[] = [
                'url' => $this->imageUrl($raw),
                'raw' => $raw,
                'is_primary' => $images === [],
            ];
        }

        return $images;
    }

    private function imageUrl(?string $image): ?string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return null;
        }
        $path = parse_url($image, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/api/v1/media/')) {
            return $path;
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return '/api/v1/media/'.str_replace('%2F', '/', rawurlencode(ltrim($image, '/')));
    }

    /**
     * @param array<string, mixed> $attributes
     * @return list<array{key: string, label: string, value: string}>
     */
    private function attributeRows(array $attributes): array
    {
        $rows = [];
        foreach ($attributes as $key => $value) {
            $rows[] = [
                'key' => (string) $key,
                'label' => ucfirst(str_replace('_', ' ', (string) $key)),
                'value' => is_scalar($value) || $value === null
                    ? (string) ($value ?? '—')
                    : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        return $rows;
    }
}

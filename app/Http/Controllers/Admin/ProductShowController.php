<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\AdminActionApproval;
use App\Models\AuditLog;
use App\Models\DisputeCase;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Support\AdminReasonCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductShowController extends AdminPageController
{
    public function __invoke(Request $request, Product $product): Response
    {
        $product->load(['seller_profile:id,display_name,user_id', 'storefront:id,title', 'category:id,name']);

        $canModerate = $request->user()?->hasPermissionCode(AdminPermission::PRODUCTS_MODERATE) ?? false;
        $qualityChecks = [
            ['label' => 'Title present', 'ok' => $product->title !== null && trim((string) $product->title) !== ''],
            ['label' => 'Description present', 'ok' => $product->description !== null && trim((string) $product->description) !== ''],
            ['label' => 'Category assigned', 'ok' => $product->category_id !== null],
            ['label' => 'Currency set', 'ok' => $product->currency !== null && trim((string) $product->currency) !== ''],
        ];
        $orderItemIds = OrderItem::query()->where('product_id', $product->id)->pluck('id');

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
            'ops_metrics' => [
                'total_order_items' => (int) OrderItem::query()->where('product_id', $product->id)->count(),
                'open_disputes' => (int) DisputeCase::query()->whereIn('order_item_id', $orderItemIds)->where('status', '!=', 'resolved')->count(),
                'avg_rating' => round((float) (Review::query()->where('product_id', $product->id)->avg('rating') ?? 0), 2),
            ],
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
}

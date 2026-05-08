<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Http\AppServices;
use App\Models\SellerProfile;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PromotionsController extends AdminPageController
{
    public function __construct(
        private readonly AppServices $app,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize($request);

        return Inertia::render('Admin/Promotions/Index', [
            'header' => $this->pageHeader(
                'Promotions',
                'Schedule catalog campaigns and promo codes with product, seller, category, and fulfillment targeting.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Promotions'],
                ],
            ),
            'rows' => $this->app->promotionService()->listAdminPromotions(),
            'store_url' => route('admin.promotions.store'),
            'update_url_template' => route('admin.promotions.update', ['promotion' => '__ID__']),
            'toggle_url_template' => route('admin.promotions.toggle', ['promotion' => '__ID__']),
            'delete_url_template' => route('admin.promotions.delete', ['promotion' => '__ID__']),
            'seller_options' => SellerProfile::query()
                ->orderBy('display_name')
                ->limit(500)
                ->get(['id', 'display_name', 'legal_name'])
                ->map(static fn (SellerProfile $seller): array => [
                    'value' => (string) $seller->id,
                    'label' => (string) ($seller->display_name ?: $seller->legal_name ?: 'Seller #'.$seller->id),
                ])
                ->values(),
            'category_options' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (Category $category): array => ['value' => (string) $category->id, 'label' => (string) $category->name])
                ->values(),
            'product_options' => Product::query()
                ->latest('id')
                ->limit(300)
                ->get(['id', 'title'])
                ->map(static fn (Product $product): array => ['value' => (string) $product->id, 'label' => '#'.$product->id.' '.$product->title])
                ->values(),
            'type_options' => [
                ['value' => 'physical', 'label' => 'Physical'],
                ['value' => 'digital', 'label' => 'Digital'],
                ['value' => 'instant_delivery', 'label' => 'Instant delivery'],
                ['value' => 'service', 'label' => 'Service'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request);

        $payload = $this->validatePayload($request);
        $created = $this->app->promotionService()->createPromotion($payload, (int) $request->user()->id);
        $this->audit($request, 'admin.promotion.created', (int) $created['id'], [], $created);

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion created.');
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize($request);

        $payload = $this->validatePayload($request, partial: true);
        $before = $promotion->toArray();
        $updated = $this->app->promotionService()->updatePromotion($promotion, $payload);
        $this->audit($request, 'admin.promotion.updated', $promotion->id, $before, $updated);

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion updated.');
    }

    public function toggle(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize($request);

        $active = filter_var((string) $request->input('is_active', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $before = ['is_active' => $promotion->is_active];
        $updated = $this->app->promotionService()->togglePromotion($promotion, $active ?? true);
        $this->audit($request, 'admin.promotion.toggled', $promotion->id, $before, ['is_active' => $updated['is_active'] ?? null]);

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion status updated.');
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize($request);

        $before = $promotion->toArray();
        $this->app->promotionService()->deletePromotion($promotion);
        $this->audit($request, 'admin.promotion.deleted', $promotion->id, $before, ['deleted' => true]);

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion deleted.');
    }

    private function authorize(Request $request): void
    {
        /** @var \App\Models\User|null $actor */
        $actor = $request->user();
        if ($actor === null || (! $actor->isPlatformStaff() && ! $actor->hasPermissionCode(AdminPermission::PROMOTIONS_MANAGE))) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'code' => [$partial ? 'sometimes' : 'required', 'string', 'max:64'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'badge' => ['nullable', 'string', 'max:48'],
            'campaign_type' => [$partial ? 'sometimes' : 'required', 'in:coupon,catalog'],
            'scope_type' => ['nullable', 'in:all,products,sellers,categories,product_types'],
            'target_product_ids' => ['nullable', 'array'],
            'target_product_ids.*' => ['integer', 'exists:products,id'],
            'target_seller_profile_ids' => ['nullable', 'array'],
            'target_seller_profile_ids.*' => ['integer', 'exists:seller_profiles,id'],
            'target_category_ids' => ['nullable', 'array'],
            'target_category_ids.*' => ['integer', 'exists:categories,id'],
            'target_product_types' => ['nullable', 'array'],
            'target_product_types.*' => ['string', 'in:physical,digital,instant_delivery,service'],
            'currency' => [$partial ? 'sometimes' : 'required', 'string', 'size:3'],
            'discount_type' => [$partial ? 'sometimes' : 'required', 'in:percentage,fixed,shipping'],
            'discount_value' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'daily_start_time' => ['nullable', 'date_format:H:i'],
            'daily_end_time' => ['nullable', 'date_format:H:i'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'marketing_channel' => ['nullable', 'string', 'max:64'],
            'used_count' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ];

        $data = $request->validate($rules);
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = filter_var((string) $data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(Request $request, string $action, int $targetId, array $before, array $after): void
    {
        /** @var \App\Models\User|null $actor */
        $actor = $request->user();
        if ($actor === null) {
            return;
        }

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: $action,
            targetType: 'promotion',
            targetId: $targetId,
            beforeJson: $before,
            afterJson: $after,
            reasonCode: 'promotion_management',
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}

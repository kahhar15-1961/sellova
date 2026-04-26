<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AdminActionApproval;
use App\Models\AuditLog;
use App\Models\DisputeCase;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\WithdrawalRequest;
use App\Support\AdminReasonCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SellerProfileShowController extends AdminPageController
{
    public function __invoke(Request $request, SellerProfile $sellerProfile): Response
    {
        $sellerProfile->load(['user:id,email,phone,status,risk_level', 'storefront:id,seller_profile_id,title,status']);

        $recentProducts = Product::query()
            ->where('seller_profile_id', $sellerProfile->id)
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'title', 'status', 'currency', 'base_price', 'updated_at'])
            ->map(static fn (Product $p): array => [
                'id' => $p->id,
                'title' => $p->title ?? '#'.$p->id,
                'status' => (string) $p->status,
                'price' => trim(($p->currency ?? '').' '.(string) $p->base_price),
                'updated_at' => $p->updated_at?->toIso8601String(),
                'href' => route('admin.products.show', $p),
            ])->all();

        $orderIds = OrderItem::query()->where('seller_profile_id', $sellerProfile->id)->pluck('order_id');
        $openDisputes = (int) DisputeCase::query()->whereIn('order_id', $orderIds)->where('status', '!=', 'resolved')->count();

        return Inertia::render('Admin/SellerProfiles/Show', [
            'header' => $this->pageHeader(
                'Seller #'.$sellerProfile->id,
                'Seller 360 profile with catalog, payout, and dispute operational signals.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Seller Profiles', 'href' => route('admin.seller-profiles.index')],
                    ['label' => $sellerProfile->display_name ?? ('#'.$sellerProfile->id)],
                ],
            ),
            'seller' => [
                'id' => $sellerProfile->id,
                'display_name' => $sellerProfile->display_name,
                'legal_name' => $sellerProfile->legal_name,
                'country_code' => $sellerProfile->country_code,
                'default_currency' => $sellerProfile->default_currency,
                'verification_status' => $sellerProfile->verification_status,
                'store_status' => $sellerProfile->store_status,
                'created_at' => $sellerProfile->created_at?->toIso8601String(),
                'account' => $sellerProfile->user ? [
                    'id' => $sellerProfile->user->id,
                    'email' => $sellerProfile->user->email,
                    'phone' => $sellerProfile->user->phone,
                    'status' => $sellerProfile->user->status,
                    'risk_level' => $sellerProfile->user->risk_level,
                ] : null,
                'storefront' => $sellerProfile->storefront ? [
                    'title' => $sellerProfile->storefront->title,
                    'status' => $sellerProfile->storefront->status,
                ] : null,
            ],
            'stats' => [
                'products_total' => (int) Product::query()->where('seller_profile_id', $sellerProfile->id)->count(),
                'pending_withdrawals' => (int) WithdrawalRequest::query()->where('seller_profile_id', $sellerProfile->id)->whereIn('status', ['requested', 'under_review'])->count(),
                'open_disputes' => $openDisputes,
            ],
            'recent_products' => $recentProducts,
            'list_href' => route('admin.seller-profiles.index'),
            'state_update_url' => route('admin.seller-profiles.update-state', $sellerProfile),
            'reason_codes' => AdminReasonCatalog::sellerStoreCodes(),
            'pending_approvals' => AdminActionApproval::query()
                ->where('target_type', 'seller_profile')
                ->where('target_id', $sellerProfile->id)
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
                ->where('target_type', 'seller_profile')
                ->where('target_id', $sellerProfile->id)
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
        ]);
    }
}

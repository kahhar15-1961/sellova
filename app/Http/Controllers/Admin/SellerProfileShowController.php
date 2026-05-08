<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Models\AdminActionApproval;
use App\Models\AuditLog;
use App\Models\DisputeCase;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Models\WithdrawalRequest;
use App\Models\Wallet;
use App\Services\WalletLedger\WalletLedgerService;
use App\Support\AdminReasonCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SellerProfileShowController extends AdminPageController
{
    public function __invoke(Request $request, SellerProfile $sellerProfile): Response
    {
        $sellerProfile->load(['user:id,email,phone,status,risk_level', 'storefront:id,seller_profile_id,title,is_public']);
        $sellerProfile->user?->loadMissing(['wallets:id,user_id,wallet_type,status,currency,version']);
        $walletLedger = app(WalletLedgerService::class);

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
        $riskScore = $this->riskScore($sellerProfile->id, $sellerProfile->user_id, $openDisputes);

        $wallets = $sellerProfile->user?->wallets ?? collect();
        $walletSummaries = $wallets->map(function (Wallet $wallet) use ($walletLedger): array {
            $balances = $walletLedger->computeWalletBalances(new ComputeWalletBalancesCommand((int) $wallet->id));
            return [
                'id' => $wallet->id,
                'type' => $wallet->wallet_type->value,
                'status' => $wallet->status->value,
                'currency' => $wallet->currency,
                'available_balance' => (string) ($balances['available_balance'] ?? '0.0000'),
                'held_balance' => (string) ($balances['held_balance'] ?? '0.0000'),
                'href' => route('admin.wallets.show', $wallet),
            ];
        })->values()->all();

        $recentWithdrawals = WithdrawalRequest::query()
            ->where('seller_profile_id', $sellerProfile->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn (WithdrawalRequest $w): array => [
                'id' => $w->id,
                'status' => $w->status->value,
                'requested_amount' => (string) $w->requested_amount,
                'fee_amount' => (string) $w->fee_amount,
                'net_payout_amount' => (string) $w->net_payout_amount,
                'currency' => $w->currency,
                'created_at' => $w->created_at?->toIso8601String(),
                'href' => route('admin.withdrawals.show', $w),
            ])->values()->all();

        $recentReviews = Review::query()
            ->where('seller_profile_id', $sellerProfile->id)
            ->with(['product:id,title', 'buyer:id,email'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn (Review $r): array => [
                'id' => $r->id,
                'product' => $r->product?->title ?? '#'.$r->product_id,
                'buyer' => $r->buyer?->email ?? '—',
                'rating' => $r->rating,
                'comment' => $r->comment,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values()->all();

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
                'risk_score' => $riskScore,
                'risk_band' => $this->riskBand($riskScore),
                'store_status' => $sellerProfile->store_status,
                'created_at' => $sellerProfile->created_at?->toIso8601String(),
                'account' => $sellerProfile->user ? [
                    'id' => $sellerProfile->user->id,
                    'email' => $sellerProfile->user->email,
                    'phone' => $sellerProfile->user->phone,
                    'status' => $sellerProfile->user->status,
                    'risk_level' => $sellerProfile->user->risk_level,
                    'href' => route('admin.users.show', $sellerProfile->user),
                ] : null,
                'storefront' => $sellerProfile->storefront ? [
                    'title' => $sellerProfile->storefront->title,
                    'is_public' => (bool) $sellerProfile->storefront->is_public,
                ] : null,
            ],
            'stats' => [
                'products_total' => (int) Product::query()->where('seller_profile_id', $sellerProfile->id)->count(),
                'pending_withdrawals' => (int) WithdrawalRequest::query()->where('seller_profile_id', $sellerProfile->id)->whereIn('status', ['requested', 'under_review'])->count(),
                'reviews_total' => (int) Review::query()->where('seller_profile_id', $sellerProfile->id)->count(),
                'payment_methods_total' => (int) UserPaymentMethod::query()->where('user_id', $sellerProfile->user_id)->count(),
                'open_disputes' => $openDisputes,
            ],
            'wallets' => $walletSummaries,
            'payment_methods' => UserPaymentMethod::query()
                ->where('user_id', $sellerProfile->user_id)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(static fn (UserPaymentMethod $m): array => [
                    'id' => $m->id,
                    'kind' => $m->kind,
                    'label' => $m->label,
                    'subtitle' => $m->subtitle,
                    'is_default' => (bool) $m->is_default,
                ])->values()->all(),
            'recent_products' => $recentProducts,
            'recent_withdrawals' => $recentWithdrawals,
            'recent_reviews' => $recentReviews,
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

    private function riskScore(int $sellerProfileId, int $userId, int $openDisputes): int
    {
        $score = 100;
        $profile = SellerProfile::query()->find($sellerProfileId);
        if ($profile === null || $profile->verification_status !== 'verified') {
            $score -= 30;
        }
        if ($profile !== null && $profile->store_status !== 'active') {
            $score -= 15;
        }
        if ($openDisputes > 0) {
            $score -= min(30, $openDisputes * 6);
        }
        $withdrawals = WithdrawalRequest::query()->where('seller_profile_id', $sellerProfileId)->whereIn('status', ['requested', 'under_review'])->count();
        $score -= min(10, $withdrawals * 2);
        $products = Product::query()->where('seller_profile_id', $sellerProfileId)->count();
        if ($products === 0) {
            $score -= 10;
        }
        $user = User::query()->find($userId);
        if ($user !== null && $user->risk_level === 'high') {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    private function riskBand(int $score): string
    {
        return $score >= 80 ? 'low' : ($score >= 50 ? 'medium' : 'high');
    }
}

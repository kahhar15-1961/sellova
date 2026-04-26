<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AdminActionApproval;
use App\Models\AuditLog;
use App\Models\DisputeCase;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Support\AdminReasonCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BuyerShowController extends AdminPageController
{
    public function __invoke(Request $request, User $buyer): Response
    {
        if ($buyer->sellerProfile()->exists()) {
            abort(404);
        }

        $buyer->load(['orders:id,buyer_user_id,order_number,status,currency,gross_amount,placed_at', 'wallets:id,user_id,wallet_type,status,currency']);

        $recentOrders = $buyer->orders()
            ->select(['id', 'order_number', 'status', 'currency', 'gross_amount', 'placed_at'])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(static fn ($o): array => [
                'id' => $o->id,
                'order_number' => $o->order_number ?? '#'.$o->id,
                'status' => $o->status->value,
                'total' => trim(($o->currency ?? '').' '.(string) $o->gross_amount),
                'placed_at' => $o->placed_at?->toIso8601String(),
            ])->all();

        $openDisputes = DisputeCase::query()
            ->whereHas('order', static fn ($q) => $q->where('buyer_user_id', $buyer->id))
            ->where('status', '!=', 'resolved')
            ->count();

        return Inertia::render('Admin/Buyers/Show', [
            'header' => $this->pageHeader(
                'Buyer #'.$buyer->id,
                'Buyer 360 profile with transaction behavior and support risk context.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Buyers', 'href' => route('admin.buyers.index')],
                    ['label' => $buyer->email ?? ('#'.$buyer->id)],
                ],
            ),
            'buyer' => [
                'id' => $buyer->id,
                'email' => $buyer->email,
                'phone' => $buyer->phone,
                'status' => $buyer->status,
                'risk_level' => $buyer->risk_level,
                'restricted_checkout' => (bool) $buyer->restricted_checkout,
                'last_login_at' => $buyer->last_login_at?->toIso8601String(),
                'created_at' => $buyer->created_at?->toIso8601String(),
            ],
            'stats' => [
                'orders_total' => (int) Order::query()->where('buyer_user_id', $buyer->id)->count(),
                'wallets_total' => (int) Wallet::query()->where('user_id', $buyer->id)->count(),
                'open_disputes' => (int) $openDisputes,
            ],
            'wallets' => $buyer->wallets->map(static fn (Wallet $w): array => [
                'id' => $w->id,
                'type' => $w->wallet_type->value,
                'status' => $w->status->value,
                'currency' => $w->currency,
                'href' => route('admin.wallets.show', $w),
            ])->values()->all(),
            'recent_orders' => $recentOrders,
            'list_href' => route('admin.buyers.index'),
            'risk_update_url' => route('admin.buyers.risk-update', $buyer),
            'reason_codes' => AdminReasonCatalog::buyerRiskCodes(),
            'pending_approvals' => AdminActionApproval::query()
                ->where('target_type', 'user')
                ->where('target_id', $buyer->id)
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
                ->where('target_type', 'user')
                ->where('target_id', $buyer->id)
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Models\Review;
use App\Models\UserPaymentMethod;
use App\Models\Wallet;
use App\Models\User;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserShowController extends AdminPageController
{
    public function __invoke(Request $request, User $user): Response
    {
        $user->load([
            'roles:id,code,name',
            'sellerProfile:id,user_id,display_name,verification_status,store_status,default_currency',
            'wallets:id,user_id,wallet_type,status,currency,version',
        ]);
        $walletLedger = app(WalletLedgerService::class);

        $recentOrders = $user->orders()
            ->select(['id', 'order_number', 'status', 'currency', 'gross_amount', 'placed_at'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn ($o): array => [
                'id' => $o->id,
                'order_number' => $o->order_number ?? '#'.$o->id,
                'status' => $o->status->value,
                'total' => trim(($o->currency ?? '').' '.(string) $o->gross_amount),
                'placed_at' => $o->placed_at?->toIso8601String(),
            ]);

        $canManage = $request->user()?->hasPermissionCode(AdminPermission::USERS_MANAGE) ?? false;

        return Inertia::render('Admin/Users/Show', [
            'header' => $this->pageHeader(
                'User #'.$user->id,
                'Identity, roles, seller profile and recent orders.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Users', 'href' => route('admin.users.index')],
                    ['label' => $user->email ?? ('#'.$user->id)],
                ],
            ),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'risk_level' => $user->risk_level,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'roles' => $user->roles->map(static fn ($r): array => [
                    'code' => $r->code,
                    'name' => $r->name,
                ])->values()->all(),
                'seller_profile' => $user->sellerProfile ? [
                    'href' => route('admin.seller-profiles.show', $user->sellerProfile),
                    'display_name' => $user->sellerProfile->display_name,
                    'verification_status' => $user->sellerProfile->verification_status,
                    'store_status' => $user->sellerProfile->store_status,
                    'default_currency' => $user->sellerProfile->default_currency,
                ] : null,
            ],
            'wallets' => $user->wallets->map(function (Wallet $wallet) use ($walletLedger): array {
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
            })->values()->all(),
            'payment_methods' => UserPaymentMethod::query()
                ->where('user_id', $user->id)
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
            'recent_reviews' => Review::query()
                ->where('buyer_user_id', $user->id)
                ->with(['product:id,title', 'seller_profile:id,display_name'])
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(static fn (Review $r): array => [
                    'id' => $r->id,
                    'product' => $r->product?->title ?? '#'.$r->product_id,
                    'seller' => $r->seller_profile?->display_name ?? '—',
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values()->all(),
            'recent_orders' => $recentOrders,
            'can_manage' => $canManage,
            'list_href' => route('admin.users.index'),
            'update_url' => route('admin.users.update-state', $user),
        ]);
    }
}

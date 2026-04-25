<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserShowController extends AdminPageController
{
    public function __invoke(Request $request, User $user): Response
    {
        $user->load(['roles:id,code,name', 'sellerProfile:id,user_id,display_name,verification_status']);

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
                    'display_name' => $user->sellerProfile->display_name,
                    'verification_status' => $user->sellerProfile->verification_status,
                ] : null,
            ],
            'recent_orders' => $recentOrders,
            'can_manage' => $canManage,
            'list_href' => route('admin.users.index'),
            'update_url' => route('admin.users.update-state', $user),
        ]);
    }
}

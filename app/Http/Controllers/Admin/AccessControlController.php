<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AccessControlController extends AdminPageController
{
    public function index(Request $request): Response
    {
        $this->ensurePermissionCatalog();

        $roles = Role::query()
            ->with(['permissions:id,code,name'])
            ->withCount('userRoles')
            ->orderBy('code')
            ->get();

        $permissions = Permission::query()
            ->whereIn('code', AdminPermission::all())
            ->orderBy('code')
            ->get()
            ->map(static fn (Permission $permission): array => [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'group' => self::permissionGroup((string) $permission->code),
            ])
            ->values()
            ->all();

        $groupedPermissions = [];
        foreach ($permissions as $permission) {
            $groupedPermissions[$permission['group']][] = $permission;
        }
        $orderedGroups = [
            'Core',
            'Users',
            'Sellers',
            'Products',
            'Orders',
            'Escrows',
            'Disputes',
            'Withdrawals',
            'Wallets',
            'Settings',
            'Promotions',
            'Audit',
            'Other',
        ];
        $groupedPermissions = array_intersect_key(array_merge(array_fill_keys($orderedGroups, []), $groupedPermissions), array_fill_keys($orderedGroups, true));
        $groupedPermissions = array_filter($groupedPermissions, static fn (array $items): bool => $items !== []);

        return Inertia::render('Admin/AccessControl/Index', [
            'header' => $this->pageHeader(
                'Access Control',
                'Manage roles and permissions used across the admin console.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Access Control'],
                ],
            ),
            'roles' => $roles->map(static fn (Role $role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'user_count' => (int) $role->user_roles_count,
                'permission_ids' => $role->permissions->pluck('id')->values()->all(),
                'permission_codes' => $role->permissions->pluck('code')->values()->all(),
                'permission_names' => $role->permissions->pluck('name')->values()->all(),
            ])->values()->all(),
            'permission_groups' => $groupedPermissions,
            'summary' => [
                'roles' => $roles->count(),
                'permissions' => count($permissions),
            ],
            'store_url' => route('admin.access-control.roles.store'),
            'update_url_template' => route('admin.access-control.roles.update', ['role' => '__ID__']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'code')],
            'name' => ['required', 'string', 'max:160'],
        ]);

        Role::query()->create([
            'code' => strtolower(trim($data['code'])),
            'name' => trim($data['name']),
        ]);

        return redirect()->route('admin.access-control.index')->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $permissionIds = array_values(array_unique(array_map('intval', $data['permissions'] ?? [])));

        DB::transaction(function () use ($role, $data, $permissionIds): void {
            $role->name = trim($data['name']);
            $role->save();

            DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->delete();

            if ($permissionIds === []) {
                return;
            }

            $now = now();
            $rows = array_map(static fn (int $permissionId): array => [
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'created_at' => $now,
            ], $permissionIds);

            DB::table('role_permissions')->insert($rows);
        });

        return redirect()->route('admin.access-control.index')->with('success', 'Role permissions updated.');
    }

    private static function permissionGroup(string $code): string
    {
        return match ($code) {
            AdminPermission::ACCESS => 'Core',
            AdminPermission::USERS_VIEW, AdminPermission::USERS_MANAGE => 'Users',
            AdminPermission::SELLERS_VIEW, AdminPermission::SELLERS_VERIFY => 'Sellers',
            AdminPermission::PRODUCTS_VIEW, AdminPermission::PRODUCTS_MODERATE => 'Products',
            AdminPermission::ORDERS_VIEW, AdminPermission::ORDERS_MANAGE => 'Orders',
            AdminPermission::ESCROWS_VIEW, AdminPermission::ESCROWS_MANAGE => 'Escrows',
            AdminPermission::DISPUTES_VIEW, AdminPermission::DISPUTES_RESOLVE => 'Disputes',
            AdminPermission::WITHDRAWALS_VIEW, AdminPermission::WITHDRAWALS_APPROVE => 'Withdrawals',
            AdminPermission::WALLETS_VIEW, AdminPermission::WALLETS_MANAGE => 'Wallets',
            AdminPermission::SETTINGS_VIEW, AdminPermission::SETTINGS_MANAGE => 'Settings',
            AdminPermission::AUDIT_VIEW => 'Audit',
            AdminPermission::PROMOTIONS_MANAGE => 'Promotions',
            default => 'Other',
        };
    }

    private function ensurePermissionCatalog(): void
    {
        foreach (AdminPermission::all() as $code) {
            Permission::query()->updateOrCreate(
                ['code' => $code],
                ['name' => ucfirst(trim(str_replace(['admin.', '_'], ['', ' '], $code)))],
            );
        }
    }
}

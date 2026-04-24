<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth\RoleCodes;
use App\Models\User;

/**
 * Centralizes admin RBAC. Super-admin bypasses individual permission rows.
 * Future: delegate to cached permission sets per user id.
 */
final class AdminAuthorizer
{
    public static function canAccessPanel(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isPlatformStaff();
    }

    /**
     * @return list<string>
     */
    public static function roleCodesForUser(User $user): array
    {
        $user->loadMissing('roles');

        return $user->roles->pluck('code')->filter()->values()->all();
    }

    /**
     * @return list<string>
     */
    public static function permissionCodesForUser(User $user): array
    {
        if ($user->hasRoleCode(RoleCodes::SuperAdmin)) {
            return AdminPermission::all();
        }

        $user->loadMissing('roles.permissions');

        $codes = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $codes[$permission->code] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * Map of permission code => granted (for Inertia page props).
     *
     * @return array<string, bool>
     */
    public static function permissionBooleanMap(User $user): array
    {
        $granted = [];
        foreach (AdminPermission::all() as $code) {
            $granted[$code] = $user->hasPermissionCode($code);
        }

        return $granted;
    }

    /**
     * @param  list<string>  $codes
     */
    public static function userHasAnyPermission(User $user, array $codes): bool
    {
        foreach ($codes as $code) {
            if ($user->hasPermissionCode($code)) {
                return true;
            }
        }

        return false;
    }

    private function __construct()
    {
    }
}

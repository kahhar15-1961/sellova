<?php

declare(strict_types=1);

use App\Admin\AdminPermission;
use App\Auth\RoleCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('remember_token', 100)->nullable()->after('password_hash');
            });
        }

        $now = now();

        $roleRows = [
            RoleCodes::SuperAdmin => 'Super administrator',
            RoleCodes::Admin => 'Administrator',
            RoleCodes::FinanceAdmin => 'Finance administrator',
            RoleCodes::DisputeOfficer => 'Dispute officer',
            RoleCodes::KycReviewer => 'KYC reviewer',
            RoleCodes::SupportAgent => 'Support agent',
            RoleCodes::Adjudicator => 'Adjudicator',
        ];

        foreach ($roleRows as $code => $name) {
            DB::table('roles')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (AdminPermission::all() as $code) {
            $label = str_replace(['admin.', '_'], ['', ' '], $code);
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['name' => ucfirst(trim($label)), 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('code', AdminPermission::all())->pluck('id', 'code');

        /** @var array<string, list<string>> $matrix */
        $matrix = [
            RoleCodes::SuperAdmin => AdminPermission::all(),
            RoleCodes::Admin => AdminPermission::all(),
            RoleCodes::FinanceAdmin => [
                AdminPermission::ACCESS,
                AdminPermission::ORDERS_VIEW,
                AdminPermission::ESCROWS_VIEW,
                AdminPermission::WALLETS_VIEW,
                AdminPermission::WALLETS_MANAGE,
                AdminPermission::WITHDRAWALS_VIEW,
                AdminPermission::WITHDRAWALS_APPROVE,
                AdminPermission::AUDIT_VIEW,
            ],
            RoleCodes::DisputeOfficer => [
                AdminPermission::ACCESS,
                AdminPermission::ORDERS_VIEW,
                AdminPermission::ESCROWS_VIEW,
                AdminPermission::DISPUTES_VIEW,
                AdminPermission::DISPUTES_RESOLVE,
                AdminPermission::AUDIT_VIEW,
            ],
            RoleCodes::KycReviewer => [
                AdminPermission::ACCESS,
                AdminPermission::USERS_VIEW,
                AdminPermission::SELLERS_VIEW,
                AdminPermission::SELLERS_VERIFY,
                AdminPermission::PRODUCTS_VIEW,
            ],
            RoleCodes::SupportAgent => [
                AdminPermission::ACCESS,
                AdminPermission::USERS_VIEW,
                AdminPermission::ORDERS_VIEW,
                AdminPermission::DISPUTES_VIEW,
                AdminPermission::PRODUCTS_VIEW,
            ],
            RoleCodes::Adjudicator => [
                AdminPermission::ACCESS,
                AdminPermission::DISPUTES_VIEW,
                AdminPermission::DISPUTES_RESOLVE,
                AdminPermission::ORDERS_VIEW,
                AdminPermission::ESCROWS_VIEW,
            ],
        ];

        foreach ($matrix as $roleCode => $permCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if ($roleId === null) {
                continue;
            }
            foreach ($permCodes as $permCode) {
                $permId = $permissionIds[$permCode] ?? null;
                if ($permId === null) {
                    continue;
                }
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('remember_token');
            });
        }

        $permCodes = AdminPermission::all();
        $permIds = DB::table('permissions')->whereIn('code', $permCodes)->pluck('id');
        if ($permIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
        }
        DB::table('permissions')->whereIn('code', $permCodes)->delete();

        $roleCodes = [
            RoleCodes::SuperAdmin,
            RoleCodes::FinanceAdmin,
            RoleCodes::DisputeOfficer,
            RoleCodes::KycReviewer,
            RoleCodes::SupportAgent,
        ];
        DB::table('roles')->whereIn('code', $roleCodes)->delete();
    }
};

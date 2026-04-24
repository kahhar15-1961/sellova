<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Admin panel permission codes (stored in {@code permissions.code}, linked via {@code role_permissions}).
 *
 * Action-level checks should use these constants; never compare raw strings in controllers.
 */
final class AdminPermission
{
    public const ACCESS = 'admin.access';

    public const USERS_VIEW = 'admin.users.view';

    public const USERS_MANAGE = 'admin.users.manage';

    public const SELLERS_VIEW = 'admin.sellers.view';

    public const SELLERS_VERIFY = 'admin.sellers.verify';

    public const PRODUCTS_VIEW = 'admin.products.view';

    public const PRODUCTS_MODERATE = 'admin.products.moderate';

    public const ORDERS_VIEW = 'admin.orders.view';

    public const ORDERS_MANAGE = 'admin.orders.manage';

    public const ESCROWS_VIEW = 'admin.escrows.view';

    public const ESCROWS_MANAGE = 'admin.escrows.manage';

    public const DISPUTES_VIEW = 'admin.disputes.view';

    public const DISPUTES_RESOLVE = 'admin.disputes.resolve';

    public const WITHDRAWALS_VIEW = 'admin.withdrawals.view';

    public const WITHDRAWALS_APPROVE = 'admin.withdrawals.approve';

    public const WALLETS_VIEW = 'admin.wallets.view';

    public const WALLETS_MANAGE = 'admin.wallets.manage';

    public const SETTINGS_VIEW = 'admin.settings.view';

    public const SETTINGS_MANAGE = 'admin.settings.manage';

    public const AUDIT_VIEW = 'admin.audit.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACCESS,
            self::USERS_VIEW,
            self::USERS_MANAGE,
            self::SELLERS_VIEW,
            self::SELLERS_VERIFY,
            self::PRODUCTS_VIEW,
            self::PRODUCTS_MODERATE,
            self::ORDERS_VIEW,
            self::ORDERS_MANAGE,
            self::ESCROWS_VIEW,
            self::ESCROWS_MANAGE,
            self::DISPUTES_VIEW,
            self::DISPUTES_RESOLVE,
            self::WITHDRAWALS_VIEW,
            self::WITHDRAWALS_APPROVE,
            self::WALLETS_VIEW,
            self::WALLETS_MANAGE,
            self::SETTINGS_VIEW,
            self::SETTINGS_MANAGE,
            self::AUDIT_VIEW,
        ];
    }

    private function __construct()
    {
    }
}

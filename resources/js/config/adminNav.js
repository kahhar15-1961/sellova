import {
    Banknote,
    BellRing,
    BookOpenCheck,
    Boxes,
    CreditCard,
    FolderKanban,
    LayoutDashboard,
    Package,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Siren,
    Store,
    Tags,
    TicketPercent,
    Truck,
    UserCog,
    Users,
    Vault,
    Wallet,
    Scale,
} from 'lucide-react';

/** @typedef {{ id: string, label: string, href?: string, icon: import('lucide-react').LucideIcon, permission?: string, badgeKey?: string, children?: NavItem[] }} NavItem */

/** @type {{ id: string, label: string, items: NavItem[] }[]} */
export const adminNavGroups = [
    {
        id: 'overview',
        label: '',
        items: [{ id: 'dashboard', label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard }],
    },
    {
        id: 'commerce',
        label: 'Commerce',
        items: [
            {
                id: 'people',
                label: 'Customer Ops',
                icon: Users,
                children: [
                    { id: 'users', label: 'Users', href: '/admin/users', icon: Users, permission: 'admin.users.view' },
                    { id: 'buyers', label: 'Buyers', href: '/admin/buyers', icon: UserCog, permission: 'admin.users.view' },
                    { id: 'seller_profiles', label: 'Seller Profiles', href: '/admin/seller-profiles', icon: Store, permission: 'admin.sellers.view' },
                    { id: 'sellers', label: 'Verification', href: '/admin/sellers', icon: ShieldCheck, permission: 'admin.sellers.view' },
                ],
            },
            {
                id: 'catalog',
                label: 'Catalog',
                icon: Boxes,
                children: [
                    { id: 'products', label: 'Products', href: '/admin/products', icon: Package, permission: 'admin.products.view' },
                    { id: 'categories', label: 'Categories', href: '/admin/categories', icon: Tags, permission: 'admin.products.moderate' },
                ],
            },
            {
                id: 'orders',
                label: 'Orders',
                icon: ShoppingCart,
                children: [
                    { id: 'orders_all', label: 'Order List', href: '/admin/orders', icon: ShoppingCart, permission: 'admin.orders.view' },
                    { id: 'escrows', label: 'Escrow Desk', href: '/admin/escrows', icon: Vault, permission: 'admin.escrows.view' },
                    { id: 'disputes', label: 'Disputes', href: '/admin/disputes', icon: Scale, permission: 'admin.disputes.view' },
                ],
            },
        ],
    },
    {
        id: 'finance',
        label: 'Finance',
        items: [
            {
                id: 'payments',
                label: 'Treasury',
                icon: CreditCard,
                children: [
                    { id: 'withdrawals', label: 'Withdrawals', href: '/admin/withdrawals', icon: Banknote, permission: 'admin.withdrawals.view' },
                    { id: 'wallet_top_ups', label: 'Wallet Top-Ups', href: '/admin/wallet-top-ups', icon: Wallet, permission: 'admin.wallets.view', badgeKey: 'wallet_top_ups' },
                    { id: 'wallets', label: 'Wallet Ledger', href: '/admin/wallets', icon: Wallet, permission: 'admin.wallets.view' },
                ],
            },
        ],
    },
    {
        id: 'system',
        label: 'Workspace',
        items: [
            {
                id: 'configuration',
                label: 'Configuration',
                icon: Settings,
                children: [
                    { id: 'settings', label: 'Settings', href: '/admin/settings', icon: Settings, permission: 'admin.settings.view' },
                    { id: 'shipping_methods', label: 'Shipping', href: '/admin/shipping-methods', icon: Truck, permission: 'admin.settings.manage' },
                    { id: 'payment_gateways', label: 'Gateways', href: '/admin/settings/payment-gateways', icon: CreditCard, permission: 'admin.settings.view' },
                    { id: 'promotions', label: 'Promotions', href: '/admin/promotions', icon: TicketPercent },
                ],
            },
            {
                id: 'governance',
                label: 'Governance',
                icon: FolderKanban,
                children: [
                    { id: 'access_control', label: 'Access Control', href: '/admin/access-control', icon: ShieldCheck, permission: 'admin.access' },
                    { id: 'approvals', label: 'Approvals', href: '/admin/approvals', icon: ScrollText, permission: 'admin.access' },
                    { id: 'escalations', label: 'Escalations', href: '/admin/escalations', icon: Siren, permission: 'admin.access' },
                    { id: 'escalation_policies', label: 'Policies', href: '/admin/escalation-policies', icon: ShieldCheck, permission: 'admin.access' },
                    { id: 'runbooks', label: 'Runbooks', href: '/admin/runbooks', icon: BookOpenCheck, permission: 'admin.access' },
                    { id: 'comms_integrations', label: 'Comms', href: '/admin/comms-integrations', icon: BellRing, permission: 'admin.access' },
                    { id: 'audit', label: 'Audit Logs', href: '/admin/audit-logs', icon: ScrollText, permission: 'admin.audit.view' },
                ],
            },
        ],
    },
];

export function flattenAdminNav(groups = adminNavGroups) {
    const normalizedGroups = Array.isArray(groups) ? groups : [];

    return normalizedGroups.reduce((allItems, group) => {
        const groupItems = Array.isArray(group?.items) ? group.items : [];

        const nextItems = groupItems.reduce((items, item) => {
            if (Array.isArray(item?.children) && item.children.length > 0) {
                return items.concat(
                    item.children.map((child) => ({
                        ...child,
                        parentLabel: item.label,
                        groupLabel: group.label,
                    })),
                );
            }

            return items.concat([{ ...item, groupLabel: group.label }]);
        }, []);

        return allItems.concat(nextItems);
    }, []);
}

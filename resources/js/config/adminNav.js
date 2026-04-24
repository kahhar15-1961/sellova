import {
    LayoutDashboard,
    Users,
    Store,
    Package,
    ShoppingCart,
    Vault,
    Scale,
    Banknote,
    Wallet,
    Settings,
    ScrollText,
} from 'lucide-react';

/** @typedef {{ id: string, label: string, href: string, icon: import('lucide-react').LucideIcon, permission?: string }} NavItem */

/** @type {{ id: string, label: string, items: NavItem[] }[]} */
export const adminNavGroups = [
    {
        id: 'overview',
        label: 'Overview',
        items: [{ id: 'dashboard', label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard }],
    },
    {
        id: 'marketplace',
        label: 'Marketplace',
        items: [
            { id: 'users', label: 'Users', href: '/admin/users', icon: Users, permission: 'admin.users.view' },
            { id: 'sellers', label: 'Sellers / Verification', href: '/admin/sellers', icon: Store, permission: 'admin.sellers.view' },
            { id: 'products', label: 'Products / Moderation', href: '/admin/products', icon: Package, permission: 'admin.products.view' },
        ],
    },
    {
        id: 'orders',
        label: 'Orders & Escrow',
        items: [
            { id: 'orders', label: 'Orders', href: '/admin/orders', icon: ShoppingCart, permission: 'admin.orders.view' },
            { id: 'escrows', label: 'Escrows', href: '/admin/escrows', icon: Vault, permission: 'admin.escrows.view' },
        ],
    },
    {
        id: 'finance',
        label: 'Finance',
        items: [
            { id: 'withdrawals', label: 'Withdrawals', href: '/admin/withdrawals', icon: Banknote, permission: 'admin.withdrawals.view' },
            { id: 'wallets', label: 'Wallets / Ledger', href: '/admin/wallets', icon: Wallet, permission: 'admin.wallets.view' },
        ],
    },
    {
        id: 'support',
        label: 'Support',
        items: [{ id: 'disputes', label: 'Disputes', href: '/admin/disputes', icon: Scale, permission: 'admin.disputes.view' }],
    },
    {
        id: 'system',
        label: 'System',
        items: [
            { id: 'settings', label: 'Settings', href: '/admin/settings', icon: Settings, permission: 'admin.settings.view' },
            { id: 'audit', label: 'Audit Logs', href: '/admin/audit-logs', icon: ScrollText, permission: 'admin.audit.view' },
        ],
    },
];

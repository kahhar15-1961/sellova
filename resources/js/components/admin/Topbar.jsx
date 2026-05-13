import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, LogOut, Menu, MessageSquare, MoonStar, PanelLeft, Search, Settings, SunMedium } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { subscribeAdminNotifications } from '@/realtime/admin_notification_binding';
import { flattenAdminNav } from '@/config/adminNav';
import { cn } from '@/lib/utils';

function formatRelativeTime(iso) {
    if (!iso) return 'Just now';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return 'Just now';

    const diffMs = date.getTime() - Date.now();
    const diffMinutes = Math.round(diffMs / 60000);
    if (Math.abs(diffMinutes) < 1) return 'Just now';
    if (Math.abs(diffMinutes) < 60) {
        return `${Math.abs(diffMinutes)}m ${diffMinutes < 0 ? 'ago' : 'from now'}`;
    }
    const diffHours = Math.round(diffMinutes / 60);
    if (Math.abs(diffHours) < 24) {
        return `${Math.abs(diffHours)}h ${diffHours < 0 ? 'ago' : 'from now'}`;
    }
    const diffDays = Math.round(diffHours / 24);
    return `${Math.abs(diffDays)}d ${diffDays < 0 ? 'ago' : 'from now'}`;
}

function isQueueNotification(templateCode) {
    return typeof templateCode === 'string' && templateCode.startsWith('admin.');
}

function shouldRefreshAdminPage(pathname, templateCode) {
    if (!isQueueNotification(templateCode)) {
        return false;
    }

    return [
        '/admin/dashboard',
        '/admin/wallet-top-ups',
        '/admin/wallets',
        '/admin/sellers',
        '/admin/withdrawals',
        '/admin/disputes',
        '/admin/approvals',
        '/admin/escalations',
    ].some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

function notificationGroupForTemplateCode(templateCode) {
    const code = (templateCode || '').toLowerCase();
    if (code.startsWith('admin.wallet_top_up') || code.startsWith('admin.withdrawal') || code.startsWith('admin.wallet')) {
        return 'Finance';
    }
    if (code.startsWith('admin.kyc') || code.startsWith('admin.seller_kyc')) {
        return 'KYC';
    }
    if (code.startsWith('admin.sla') || code.startsWith('admin.escalation') || code.startsWith('admin.audit')) {
        return 'System';
    }

    return 'System';
}

function groupNotifications(items) {
    const groups = { Finance: [], KYC: [], System: [] };
    for (const item of items || []) {
        const group = notificationGroupForTemplateCode(item.template_code);
        groups[group].push(item);
    }
    return groups;
}

function countUnread(items) {
    return (items || []).filter((item) => !item.is_read).length;
}

function buildNotificationHref(notification) {
    const code = (notification?.template_code || '').toLowerCase();
    const payload = notification?.payload && typeof notification.payload === 'object' ? notification.payload : {};
    const idFromPayload =
        payload.wallet_top_up_request_id ??
        payload.kyc_id ??
        payload.incident_id ??
        payload.target_id ??
        payload.dispute_id ??
        payload.withdrawal_id;

    if (typeof notification?.href === 'string' && notification.href.trim() !== '') {
        return notification.href.trim();
    }

    if (code.startsWith('admin.wallet_top_up') && idFromPayload) {
        return `/admin/wallet-top-ups/${Number(idFromPayload)}`;
    }
    if (code.startsWith('admin.kyc') && idFromPayload) {
        return `/admin/sellers/kyc/${Number(idFromPayload)}`;
    }
    if (code.startsWith('admin.sla') || code.startsWith('admin.escalation')) {
        if ((payload.queue || payload.queue_code || '') === 'seller_kyc' && idFromPayload) {
            return `/admin/sellers/kyc/${Number(idFromPayload)}`;
        }
        if ((payload.queue || payload.queue_code || '') === 'disputes' && idFromPayload) {
            return `/admin/disputes/${Number(idFromPayload)}`;
        }
        if ((payload.queue || payload.queue_code || '') === 'withdrawals' && idFromPayload) {
            return `/admin/withdrawals/${Number(idFromPayload)}`;
        }
        if (idFromPayload) {
            return `/admin/escalations/${Number(idFromPayload)}`;
        }
    }

    return '';
}

const THEME_STORAGE_KEY = 'sellova-admin-theme';

/**
 * @param {{ onOpenSidebar?: () => void, collapsed?: boolean }} props
 */
export function Topbar({ onOpenSidebar, collapsed = false }) {
    const page = usePage();
    const { auth, adminNotifications: initialNotifications = [], adminNotificationCount: initialUnreadCount = 0 } = page.props;
    const headerMeta = page.props.header && typeof page.props.header === 'object' ? page.props.header : null;
    const user = auth?.user;
    const initials = user?.email?.slice(0, 2).toUpperCase() ?? 'AD';
    const pathname = page.url || '';
    const pageTitle = typeof headerMeta?.title === 'string' && headerMeta.title.trim() !== '' ? headerMeta.title : 'Workspace';
    const pageDescription =
        typeof headerMeta?.description === 'string' && headerMeta.description.trim() !== ''
            ? headerMeta.description
            : 'Admin operations';

    const [notifications, setNotifications] = useState(initialNotifications);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const [theme, setTheme] = useState('light');
    const searchRef = useRef(null);
    const reloadTimerRef = useRef(null);

    useEffect(() => {
        setNotifications(initialNotifications);
    }, [initialNotifications]);

    useEffect(() => {
        setUnreadCount(initialUnreadCount);
    }, [initialUnreadCount]);

    useEffect(() => {
        if (!user?.id) {
            return undefined;
        }

        return subscribeAdminNotifications(user.id, (notification, unread) => {
            const nextNotification = {
                id: notification?.id ?? notification?.uuid ?? `${Date.now()}`,
                template_code: notification?.template_code ?? '',
                title: notification?.title ?? notification?.template_code ?? 'Notification',
                body: notification?.body ?? '',
                href: buildNotificationHref(notification),
                payload: notification?.payload && typeof notification.payload === 'object' ? notification.payload : {},
                is_read: Boolean(notification?.is_read),
                created_at: notification?.created_at ?? new Date().toISOString(),
            };

            setUnreadCount(typeof unread === 'number' && Number.isFinite(unread) ? unread : (prev) => prev + (nextNotification.is_read ? 0 : 1));
            setNotifications((prev) => {
                const next = [nextNotification, ...(prev || []).filter((item) => item.id !== nextNotification.id)];
                return next.slice(0, 8);
            });

            if (shouldRefreshAdminPage(pathname, nextNotification.template_code)) {
                if (reloadTimerRef.current) {
                    clearTimeout(reloadTimerRef.current);
                }
                reloadTimerRef.current = setTimeout(() => {
                    router.reload({
                        preserveScroll: true,
                        preserveState: true,
                    });
                }, 600);
            }
        });
    }, [pathname, user?.id]);

    useEffect(
        () => () => {
            if (reloadTimerRef.current) {
                clearTimeout(reloadTimerRef.current);
            }
        },
        [],
    );

    useEffect(() => {
        const onClick = (event) => {
            if (!searchRef.current?.contains(event.target)) {
                setSearchOpen(false);
            }
        };

        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const stored = window.localStorage.getItem(THEME_STORAGE_KEY);
        const initialTheme = stored === 'dark' ? 'dark' : 'light';
        setTheme(initialTheme);
        document.documentElement.classList.toggle('dark', initialTheme === 'dark');
    }, []);

    const markAllRead = async () => {
        try {
            router.post(
                '/admin/notifications/read-all',
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        setUnreadCount(0);
                        setNotifications((prev) => prev.map((item) => ({ ...item, is_read: true })));
                        router.reload({
                            preserveScroll: true,
                            preserveState: false,
                        });
                    },
                },
            );
        } catch {
            // Keep the dropdown usable even if the API hiccups.
        }
    };

    const visibleNotifications = useMemo(() => notifications.slice(0, 6), [notifications]);
    const groupedNotifications = useMemo(() => groupNotifications(visibleNotifications), [visibleNotifications]);
    const groupedEntries = useMemo(
        () =>
            [
                ['Finance', groupedNotifications.Finance],
                ['KYC', groupedNotifications.KYC],
                ['System', groupedNotifications.System],
            ].filter(([, items]) => items.length > 0),
        [groupedNotifications],
    );
    const groupedUnread = useMemo(
        () => ({
            Finance: countUnread(groupedNotifications.Finance),
            KYC: countUnread(groupedNotifications.KYC),
            System: countUnread(groupedNotifications.System),
        }),
        [groupedNotifications],
    );

    const navLinks = useMemo(
        () =>
            flattenAdminNav(page.props.adminQueueCounts ?? {})
                .filter((item) => item.href)
                .map((item) => ({ ...item, search: `${item.label} ${item.groupLabel || ''}`.toLowerCase() })),
        [page.props.adminQueueCounts],
    );

    const filteredLinks = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return navLinks.slice(0, 8);
        return navLinks.filter((item) => item.search.includes(query)).slice(0, 8);
    }, [navLinks, searchQuery]);

    const openNotification = (notification) => {
        if (!notification?.id) {
            return;
        }

        const href = buildNotificationHref(notification);
        setNotifications((prev) =>
            prev.map((item) =>
                item.id === notification.id
                    ? {
                          ...item,
                          is_read: true,
                      }
                    : item,
            ),
        );
        setUnreadCount((prev) => Math.max(0, prev - (notification.is_read ? 0 : 1)));

        router.post(`/admin/notifications/${notification.id}/read`, {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (href !== '') {
                    router.visit(href, {
                        preserveScroll: true,
                        preserveState: false,
                    });
                }
            },
        });
    };

    const toggleTheme = () => {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        setTheme(nextTheme);
        document.documentElement.classList.toggle('dark', nextTheme === 'dark');
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
        }
    };

    return (
        <header className="sticky top-0 z-40 border-b border-border/70 bg-card/78 backdrop-blur-xl">
            <div className="mx-auto flex h-16 w-full max-w-[1600px] items-center gap-3 px-4 sm:px-5 lg:px-6">
                <div className="flex items-center gap-2 lg:hidden">
                    <Button type="button" variant="outline" size="icon" className="h-9 w-9 rounded-xl" onClick={onOpenSidebar} aria-label="Open menu">
                        <Menu className="h-4.5 w-4.5" />
                    </Button>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-foreground">{pageTitle}</p>
                    </div>
                </div>

                <div className="hidden min-w-0 items-center gap-3 text-muted-foreground lg:flex">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-border/70 bg-card shadow-sm">
                        <PanelLeft className="h-4 w-4 opacity-70" />
                    </div>
                    <div className={cn('min-w-0 transition-all duration-200', collapsed && 'opacity-80')}>
                        <p className="truncate text-sm font-semibold text-foreground">{pageTitle}</p>
                        <p className="truncate text-xs text-muted-foreground">{pageDescription}</p>
                    </div>
                </div>

                <div className="mx-auto flex max-w-xl flex-1 justify-center lg:justify-start">
                    <div ref={searchRef} className="relative w-full max-w-xl">
                        <button
                            type="button"
                            onClick={() => setSearchOpen((value) => !value)}
                            className="flex h-11 w-full items-center gap-3 rounded-2xl border border-border/75 bg-secondary/55 px-4 text-left shadow-sm transition hover:border-border"
                        >
                            <Search className="h-4 w-4 text-muted-foreground" />
                            <span className="flex-1 text-sm text-muted-foreground">
                                Search pages, queues, and tools
                            </span>
                            <span className="hidden rounded-lg border border-border/70 bg-card px-2 py-1 text-[10px] font-semibold text-muted-foreground sm:inline-flex">
                                ⌘K
                            </span>
                        </button>

                        {searchOpen ? (
                            <div className="absolute left-0 right-0 top-[calc(100%+0.75rem)] z-50 rounded-[1.35rem] border border-border/80 bg-card/98 p-3 shadow-card backdrop-blur-xl">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        autoFocus
                                        value={searchQuery}
                                        onChange={(event) => setSearchQuery(event.target.value)}
                                        className="ds-control pl-10"
                                        placeholder="Search navigation"
                                    />
                                </div>
                                <div className="mt-3 max-h-80 space-y-1 overflow-auto">
                                    {filteredLinks.map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            onClick={() => {
                                                setSearchOpen(false);
                                                setSearchQuery('');
                                            }}
                                            className="flex items-center gap-3 rounded-xl px-3 py-2 transition hover:bg-accent/70"
                                        >
                                            <item.icon className="h-4 w-4 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-foreground">{item.label}</p>
                                                <p className="truncate text-xs text-muted-foreground">{item.groupLabel}</p>
                                            </div>
                                            {item.badgeCount ? (
                                                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
                                                    {item.badgeCount}
                                                </span>
                                            ) : null}
                                        </Link>
                                    ))}
                                    {filteredLinks.length === 0 ? (
                                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">No matching pages found.</p>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>

                <div className="flex items-center justify-end gap-2 sm:gap-3">
                    <Button type="button" variant="outline" size="icon" className="h-10 w-10 rounded-xl" onClick={toggleTheme} aria-label="Toggle theme">
                        {theme === 'dark' ? <SunMedium className="h-4 w-4" /> : <MoonStar className="h-4 w-4" />}
                    </Button>

                    <Button type="button" variant="outline" size="icon" className="hidden h-10 w-10 rounded-xl sm:inline-flex">
                        <MessageSquare className="h-4 w-4" />
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="relative h-10 w-10 rounded-xl p-0 sm:h-11 sm:w-11">
                                <Bell className="h-4 w-4" />
                                {unreadCount > 0 ? (
                                    <span className="absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white shadow-sm">
                                        {unreadCount > 99 ? '99+' : unreadCount}
                                    </span>
                                ) : null}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-[min(92vw,24rem)] space-y-3 p-3">
                            <div className="flex items-center justify-between gap-3">
                                <DropdownMenuLabel className="p-0 text-sm font-semibold">Notifications</DropdownMenuLabel>
                                <Button type="button" variant="ghost" size="sm" onClick={markAllRead} disabled={unreadCount === 0}>
                                    Mark all read
                                </Button>
                            </div>
                            <DropdownMenuSeparator />
                            {visibleNotifications.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-3 py-6 text-center text-sm text-muted-foreground">
                                    No admin notifications yet.
                                </p>
                            ) : (
                                <div className="max-h-96 space-y-2 overflow-auto pr-1">
                                    {groupedEntries.map(([group, items]) => (
                                        <div key={group} className="space-y-2">
                                            <div className="flex items-center justify-between px-1">
                                                <p className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{group}</p>
                                                <span className="text-[11px] text-muted-foreground">
                                                    {groupedUnread[group] > 0 ? `${groupedUnread[group]} unread` : `${items.length} total`}
                                                </span>
                                            </div>
                                            <div className="space-y-2">
                                                {items.map((notification) => {
                                                    return (
                                                        <button
                                                            key={notification.id}
                                                            type="button"
                                                            className="block w-full rounded-xl border border-border/70 bg-card px-3 py-3 text-left transition-colors hover:border-primary/30 hover:bg-muted/20"
                                                            onClick={() => openNotification(notification)}
                                                        >
                                                            <div className="flex items-start gap-3">
                                                                <div
                                                                    className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${notification.is_read ? 'bg-muted-foreground/40' : 'bg-primary'}`}
                                                                />
                                                                <div className="min-w-0 flex-1">
                                                                    <div className="flex items-start justify-between gap-2">
                                                                        <p className="truncate text-sm font-medium text-foreground">{notification.title}</p>
                                                                        <span className="shrink-0 text-[11px] text-muted-foreground">
                                                                            {formatRelativeTime(notification.created_at)}
                                                                        </span>
                                                                    </div>
                                                                    {notification.body ? (
                                                                        <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground">{notification.body}</p>
                                                                    ) : null}
                                                                </div>
                                                            </div>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-11 gap-2 rounded-2xl border border-transparent px-2 hover:border-border/70 hover:bg-accent/65">
                                <Avatar className="h-8 w-8">
                                    <AvatarFallback>{initials}</AvatarFallback>
                                </Avatar>
                                <div className="hidden text-left sm:block">
                                    <p className="max-w-[140px] truncate text-sm font-semibold text-foreground">{user?.email}</p>
                                    <p className="text-[11px] text-muted-foreground">Admin session</p>
                                </div>
                                <ChevronDown className="hidden h-4 w-4 text-muted-foreground sm:block" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-60">
                            <DropdownMenuLabel className="space-y-1">
                                <p className="text-sm font-semibold text-foreground">{user?.email}</p>
                                <p className="text-xs font-medium text-muted-foreground">Administrator</p>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/admin/settings">
                                    <Settings className="mr-2 h-4 w-4" />
                                    Settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post('/admin/logout', undefined, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <LogOut className="mr-2 h-4 w-4" />
                                Sign out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>
    );
}

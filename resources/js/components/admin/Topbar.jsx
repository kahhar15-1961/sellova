import { useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Bell, LogOut, Menu, PanelLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Separator } from '@/components/ui/separator';
import { subscribeAdminNotifications } from '@/realtime/admin_notification_binding';

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
    if (
        code.startsWith('admin.wallet_top_up') ||
        code.startsWith('admin.withdrawal') ||
        code.startsWith('admin.wallet')
    ) {
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
    const idFromPayload = payload.wallet_top_up_request_id ?? payload.kyc_id ?? payload.incident_id ?? payload.target_id ?? payload.dispute_id ?? payload.withdrawal_id;

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

/**
 * @param {{ onOpenSidebar?: () => void }} props
 */
export function Topbar({ onOpenSidebar }) {
    const page = usePage();
    const { auth, adminNotifications: initialNotifications = [], adminNotificationCount: initialUnreadCount = 0 } = page.props;
    const user = auth?.user;
    const initials = user?.email?.slice(0, 2).toUpperCase() ?? '—';
    const pathname = page.url || '';

    const [notifications, setNotifications] = useState(initialNotifications);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount);
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

    useEffect(() => {
        return () => {
            if (reloadTimerRef.current) {
                clearTimeout(reloadTimerRef.current);
            }
        };
    }, []);

    const markAllRead = async () => {
        try {
            router.post('/admin/notifications/read-all', {}, {
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
            });
        } catch {
            // Keep the dropdown usable even if the API hiccups.
        }
    };

    const visibleNotifications = useMemo(() => notifications.slice(0, 6), [notifications]);
    const groupedNotifications = useMemo(() => groupNotifications(visibleNotifications), [visibleNotifications]);
    const groupedEntries = useMemo(
        () => [
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

    return (
        <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-border/80 bg-card/90 px-4 backdrop-blur-md lg:px-6">
            <div className="flex items-center gap-2 lg:hidden">
                <Button type="button" variant="ghost" size="icon" className="shrink-0" onClick={onOpenSidebar} aria-label="Open menu">
                    <Menu className="h-5 w-5" />
                </Button>
            </div>
            <div className="hidden items-center gap-2 text-muted-foreground lg:flex">
                <PanelLeft className="h-4 w-4 opacity-60" />
                <span className="text-xs font-medium uppercase tracking-wide">Console</span>
            </div>
            <Separator orientation="vertical" className="hidden h-6 lg:block" />
            <div className="flex flex-1 items-center justify-end gap-3">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="hidden text-xs text-muted-foreground sm:inline">Enterprise admin</span>
                    </TooltipTrigger>
                    <TooltipContent>Session-based staff access · separate from mobile API tokens</TooltipContent>
                </Tooltip>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="relative h-9 w-9 rounded-full p-0">
                            <Bell className="h-4 w-4" />
                            {unreadCount > 0 ? (
                                <span className="absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white shadow-sm">
                                    {unreadCount > 99 ? '99+' : unreadCount}
                                </span>
                            ) : null}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-[min(92vw,22rem)] space-y-3 p-3">
                        <div className="flex items-center justify-between gap-3">
                            <DropdownMenuLabel className="p-0 text-sm font-semibold">Notifications</DropdownMenuLabel>
                            <Button type="button" variant="ghost" size="sm" onClick={markAllRead} disabled={unreadCount === 0}>
                                Mark all read
                            </Button>
                        </div>
                        <DropdownMenuSeparator />
                        {visibleNotifications.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-border/70 bg-muted/20 px-3 py-6 text-center text-sm text-muted-foreground">
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
                        <Button variant="ghost" className="relative h-9 gap-2 rounded-full px-2">
                            <Avatar className="h-8 w-8">
                                <AvatarFallback>{initials}</AvatarFallback>
                            </Avatar>
                            <span className="hidden max-w-[140px] truncate text-sm font-medium sm:inline">{user?.email}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>Account</DropdownMenuLabel>
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
        </header>
    );
}

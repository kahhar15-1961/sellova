import { Link } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { NotificationEmptyState } from '@/components/notifications/NotificationEmptyState';
import { NotificationItem } from '@/components/notifications/NotificationItem';
import { NotificationSkeleton } from '@/components/notifications/NotificationSkeleton';
import { cn } from '@/lib/utils';

export function NotificationDropdown({
    open = false,
    notifications = [],
    unreadCount = 0,
    loading = false,
    error = '',
    onRefresh,
    onMarkRead,
    onMarkAllRead,
    onDelete,
    onClearAll,
    viewAllHref = '/notifications',
}) {
    return (
        <div
            className={cn(
                'pointer-events-none absolute right-0 top-[calc(100%+10px)] z-[70] w-[min(92vw,400px)] origin-top-right rounded-2xl border border-slate-200 bg-white opacity-0 shadow-xl transition duration-200 md:w-[400px]',
                'max-md:fixed max-md:left-3 max-md:right-3 max-md:top-[76px] max-md:w-auto max-md:origin-top',
                open && 'pointer-events-auto translate-y-0 scale-100 opacity-100',
                !open && '-translate-y-2 scale-[0.98]',
            )}
        >
            <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-4 py-4">
                <div>
                    <h2 className="text-xl font-black tracking-tight text-slate-950">Notifications</h2>
                    <p className="mt-1 text-xs font-medium text-slate-500">{unreadCount ? `${unreadCount} unread update${unreadCount === 1 ? '' : 's'}` : 'All caught up'}</p>
                </div>
                <div className="flex shrink-0 items-center gap-3 pt-0.5">
                    {unreadCount ? (
                        <button type="button" onClick={onMarkAllRead} className="text-xs font-bold text-slate-700 transition hover:text-slate-950">
                            Mark all as read
                        </button>
                    ) : null}
                    {notifications.length ? (
                        <button type="button" onClick={onClearAll} className="text-xs font-bold text-slate-400 transition hover:text-slate-700">
                            Clear all
                        </button>
                    ) : null}
                </div>
            </div>

            <div className="max-h-[min(70vh,520px)] overflow-y-auto px-3 py-3">
                {loading ? <NotificationSkeleton /> : null}

                {!loading && error ? (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-4">
                        <div className="flex items-start gap-3">
                            <span className="mt-0.5 flex size-9 items-center justify-center rounded-full bg-white text-rose-500 ring-1 ring-rose-100">
                                <AlertCircle className="size-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h3 className="text-sm font-bold text-slate-950">Could not load notifications</h3>
                                <p className="mt-1.5 text-sm font-medium leading-6 text-slate-500">{error}</p>
                                <button type="button" onClick={onRefresh} className="mt-3 text-xs font-bold text-rose-600 transition hover:text-rose-700">
                                    Try again
                                </button>
                            </div>
                        </div>
                    </div>
                ) : null}

                {!loading && !error && notifications.length === 0 ? <NotificationEmptyState /> : null}

                {!loading && !error && notifications.length > 0 ? (
                    <div className="grid gap-2.5">
                        {notifications.map((notification) => (
                            <NotificationItem
                                key={notification.id}
                                notification={notification}
                                onMarkRead={onMarkRead}
                                onDelete={onDelete}
                            />
                        ))}
                    </div>
                ) : null}
            </div>

            <div className="border-t border-slate-100 px-4 py-3">
                <Link href={viewAllHref} className="block text-center text-sm font-bold text-slate-700 transition hover:text-slate-950">
                    View all notifications
                </Link>
            </div>
        </div>
    );
}

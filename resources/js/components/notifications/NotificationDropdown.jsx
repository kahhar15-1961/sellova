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
                'pointer-events-none absolute right-0 top-[calc(100%+14px)] z-[70] w-[min(92vw,460px)] origin-top-right rounded-[30px] border border-slate-200/90 bg-white/95 opacity-0 shadow-[0_34px_90px_-42px_rgba(15,23,42,0.42)] ring-1 ring-slate-100 backdrop-blur transition duration-200 md:w-[460px]',
                'max-md:fixed max-md:left-3 max-md:right-3 max-md:top-[76px] max-md:w-auto max-md:origin-top',
                open && 'pointer-events-auto translate-y-0 scale-100 opacity-100',
                !open && '-translate-y-2 scale-[0.98]',
            )}
        >
            <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 className="text-[32px] font-black tracking-tight text-slate-950">Notifications</h2>
                    <p className="mt-1 text-sm font-semibold text-slate-500">{unreadCount ? `${unreadCount} unread update${unreadCount === 1 ? '' : 's'}` : 'All caught up'}</p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    {unreadCount ? (
                        <button type="button" onClick={onMarkAllRead} className="text-sm font-black text-indigo-500 transition hover:text-indigo-600">
                            Mark all as read
                        </button>
                    ) : null}
                    {notifications.length ? (
                        <button type="button" onClick={onClearAll} className="text-sm font-bold text-slate-400 transition hover:text-rose-500">
                            Clear all
                        </button>
                    ) : null}
                </div>
            </div>

            <div className="max-h-[min(70vh,560px)] overflow-y-auto px-4 py-4">
                {loading ? <NotificationSkeleton /> : null}

                {!loading && error ? (
                    <div className="rounded-[24px] border border-rose-100 bg-rose-50 px-5 py-5">
                        <div className="flex items-start gap-3">
                            <span className="mt-0.5 flex size-10 items-center justify-center rounded-full bg-white text-rose-500 ring-1 ring-rose-100">
                                <AlertCircle className="size-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h3 className="text-sm font-extrabold text-slate-950">Could not load notifications</h3>
                                <p className="mt-2 text-sm font-medium leading-6 text-slate-500">{error}</p>
                                <button type="button" onClick={onRefresh} className="mt-4 text-sm font-black text-rose-500 transition hover:text-rose-600">
                                    Try again
                                </button>
                            </div>
                        </div>
                    </div>
                ) : null}

                {!loading && !error && notifications.length === 0 ? <NotificationEmptyState /> : null}

                {!loading && !error && notifications.length > 0 ? (
                    <div className="grid gap-3">
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

            <div className="border-t border-slate-100 px-6 py-4">
                <Link href={viewAllHref} className="block text-center text-sm font-black text-slate-700 transition hover:text-indigo-600">
                    View all notifications
                </Link>
            </div>
        </div>
    );
}

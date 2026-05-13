import { Link } from '@inertiajs/react';
import { Check, Trash2 } from 'lucide-react';
import { NotificationIcon } from '@/components/notifications/NotificationIcon';
import { cn } from '@/lib/utils';

export function NotificationItem({ notification, onMarkRead, onDelete }) {
    const isRead = Boolean(notification?.is_read ?? notification?.read);
    const context = String(notification?.recipient_context || notification?.context || notification?.role || 'buyer').toLowerCase();
    const contextLabel = context.charAt(0).toUpperCase() + context.slice(1);
    const contextClass = context === 'seller'
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
        : context === 'admin'
            ? 'bg-amber-50 text-amber-700 ring-amber-100'
            : 'bg-indigo-50 text-indigo-700 ring-indigo-100';

    return (
        <div className={cn('group relative overflow-hidden rounded-xl border px-3.5 py-3 transition duration-200', isRead ? 'border-slate-200 bg-white hover:border-slate-300' : 'border-slate-200 bg-slate-50/70')}>
            {!isRead ? <span className="absolute left-0 top-0 h-full w-0.5 bg-slate-900" /> : null}
            <div className="flex items-start gap-3">
                <NotificationIcon icon={notification?.icon} color={notification?.color} />
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-3">
                        <Link href={notification?.action_url || notification?.href || '#'} onClick={() => !isRead && onMarkRead?.(notification)} className="min-w-0 pr-2">
                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                <span className={cn('inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em] ring-1', contextClass)}>{contextLabel}</span>
                                <p className="truncate text-sm font-bold tracking-tight text-slate-950">{notification?.title || 'Notification'}</p>
                            </div>
                            <p className="mt-1.5 text-sm font-medium leading-6 text-slate-500">{notification?.message || notification?.body || 'No additional details were provided.'}</p>
                        </Link>
                        <div className="flex shrink-0 items-center gap-1">
                            {!isRead ? (
                                <button
                                    type="button"
                                    onClick={() => onMarkRead?.(notification)}
                                    className="inline-flex size-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                    aria-label="Mark notification as read"
                                >
                                    <Check className="size-3.5" />
                                </button>
                            ) : null}
                            <button
                                type="button"
                                onClick={() => onDelete?.(notification)}
                                className="inline-flex size-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Delete notification"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div className="mt-3 flex items-center gap-3">
                        <span className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">{notification?.time_ago || notification?.time || 'Just now'}</span>
                        {!isRead ? <span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-700">Unread</span> : null}
                    </div>
                </div>
            </div>
        </div>
    );
}

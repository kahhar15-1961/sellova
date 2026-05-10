import { Link } from '@inertiajs/react';
import { Check, Trash2 } from 'lucide-react';
import { NotificationIcon } from '@/components/notifications/NotificationIcon';
import { cn } from '@/lib/utils';

export function NotificationItem({ notification, onMarkRead, onDelete }) {
    const isRead = Boolean(notification?.is_read ?? notification?.read);

    return (
        <div className={cn('group relative overflow-hidden rounded-[26px] border px-4 py-4 transition duration-200', isRead ? 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-[0_18px_42px_-34px_rgba(15,23,42,0.32)]' : 'border-emerald-100 bg-gradient-to-r from-emerald-50/70 via-white to-white shadow-[0_22px_44px_-34px_rgba(16,185,129,0.35)]')}>
            {!isRead ? <span className="absolute left-0 top-0 h-full w-1 rounded-full bg-emerald-400" /> : null}
            <div className="flex items-start gap-4">
                <NotificationIcon icon={notification?.icon} color={notification?.color} />
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-3">
                        <Link href={notification?.action_url || notification?.href || '#'} className="min-w-0 pr-2">
                            <p className="truncate text-[15px] font-extrabold tracking-tight text-slate-950">{notification?.title || 'Notification'}</p>
                            <p className="mt-2 text-sm font-medium leading-6 text-slate-500">{notification?.message || notification?.body || 'No additional details were provided.'}</p>
                        </Link>
                        <div className="flex shrink-0 items-center gap-1">
                            {!isRead ? (
                                <button
                                    type="button"
                                    onClick={() => onMarkRead?.(notification)}
                                    className="inline-flex size-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-600"
                                    aria-label="Mark notification as read"
                                >
                                    <Check className="size-4" />
                                </button>
                            ) : null}
                            <button
                                type="button"
                                onClick={() => onDelete?.(notification)}
                                className="inline-flex size-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-rose-50 hover:text-rose-500"
                                aria-label="Delete notification"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center gap-3">
                        <span className="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">{notification?.time_ago || notification?.time || 'Just now'}</span>
                        {!isRead ? <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Unread</span> : null}
                    </div>
                </div>
            </div>
        </div>
    );
}

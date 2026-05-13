import { useEffect, useMemo, useRef, useState } from 'react';
import { Bell } from 'lucide-react';
import { NotificationBadge } from '@/components/notifications/NotificationBadge';
import { NotificationDropdown } from '@/components/notifications/NotificationDropdown';
import { cn } from '@/lib/utils';

export function NotificationBell({
    notifications = [],
    unreadCount = 0,
    onRefresh,
    onMarkRead,
    onMarkAllRead,
    onDelete,
    onClearAll,
    viewAllHref = '/notifications',
}) {
    const [open, setOpen] = useState(false);
    const [animate, setAnimate] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const previousUnreadRef = useRef(unreadCount);
    const rootRef = useRef(null);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const handlePointer = (event) => {
            if (!rootRef.current?.contains(event.target)) {
                setOpen(false);
            }
        };

        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handlePointer);
        document.addEventListener('touchstart', handlePointer, { passive: true });
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('mousedown', handlePointer);
            document.removeEventListener('touchstart', handlePointer);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [open]);

    useEffect(() => {
        if (unreadCount > previousUnreadRef.current) {
            setAnimate(true);
            const timer = window.setTimeout(() => setAnimate(false), 1800);
            previousUnreadRef.current = unreadCount;

            return () => window.clearTimeout(timer);
        }

        previousUnreadRef.current = unreadCount;

        return undefined;
    }, [unreadCount]);

    const visibleNotifications = useMemo(() => notifications.slice(0, 8), [notifications]);

    const runAction = async (callback, ...args) => {
        if (typeof callback !== 'function') {
            return;
        }

        setLoading(true);
        setError('');

        try {
            await callback(...args);
        } catch (actionError) {
            setError(actionError?.message || 'Something went wrong while updating notifications.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div ref={rootRef} className="relative">
            <button
                type="button"
                onClick={() => {
                    const nextOpen = !open;
                    setOpen(nextOpen);
                    if (nextOpen) {
                        void runAction(onRefresh);
                    }
                }}
                className={cn('relative inline-flex size-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900', animate && 'animate-[pulse_1.6s_ease-in-out_2]')}
                aria-label="Open notifications"
                aria-expanded={open}
            >
                <Bell className={cn('size-4.5', unreadCount ? 'text-slate-900' : 'text-slate-500')} />
                <NotificationBadge count={unreadCount} />
            </button>

            <NotificationDropdown
                open={open}
                notifications={visibleNotifications}
                unreadCount={unreadCount}
                loading={loading}
                error={error}
                onRefresh={() => void runAction(onRefresh)}
                onMarkRead={(notification) => void runAction(onMarkRead, notification)}
                onMarkAllRead={() => void runAction(onMarkAllRead)}
                onDelete={(notification) => void runAction(onDelete, notification)}
                onClearAll={() => void runAction(onClearAll)}
                viewAllHref={viewAllHref}
            />
        </div>
    );
}

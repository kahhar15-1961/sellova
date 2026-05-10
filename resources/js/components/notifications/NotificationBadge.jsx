import { cn } from '@/lib/utils';

export function NotificationBadge({ count = 0, className }) {
    if (!count) {
        return null;
    }

    return (
        <span className={cn('absolute -right-1.5 -top-1.5 inline-flex min-w-[1.2rem] items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-black leading-none text-white shadow-[0_8px_18px_-10px_rgba(244,63,94,0.8)] ring-2 ring-white', className)}>
            {count > 99 ? '99+' : count}
        </span>
    );
}

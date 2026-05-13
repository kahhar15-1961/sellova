import { cn } from '@/lib/utils';

export function NotificationBadge({ count = 0, className }) {
    if (!count) {
        return null;
    }

    return (
        <span className={cn('absolute -right-1.5 -top-1.5 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-slate-900 px-1.5 py-0.5 text-[9px] font-black leading-none text-white ring-2 ring-white', className)}>
            {count > 99 ? '99+' : count}
        </span>
    );
}

import { Link, usePage } from '@inertiajs/react';
import { adminNavGroups } from '@/config/adminNav';
import { cn } from '@/lib/utils';
import { useAdminCan } from '@/hooks/useAdminCan';

function QueueBadge({ count }) {
    if (!count) return null;
    return (
        <span className="ml-auto inline-flex min-w-6 items-center justify-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
            {count > 99 ? '99+' : count}
        </span>
    );
}

/**
 * @param {{ className?: string, onNavigate?: () => void }} props
 */
export function SidebarNav({ className, onNavigate }) {
    const page = usePage();
    const url = page.url;
    const queueCounts = page.props.adminQueueCounts ?? {};
    const can = useAdminCan();

    return (
        <nav className={cn('flex flex-1 flex-col gap-6', className)}>
            <div className="px-3">
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Sellova</p>
                <p className="text-lg font-semibold tracking-tight text-foreground">Admin</p>
            </div>
            {adminNavGroups.map((group) => (
                <div key={group.id}>
                    <p className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/90">{group.label}</p>
                    <ul className="space-y-0.5">
                        {group.items
                            .filter((item) => !item.permission || can(item.permission))
                            .map((item) => {
                                const active = url === item.href || url.startsWith(`${item.href}/`);
                                const Icon = item.icon;
                                const count = item.id === 'wallet_top_ups' ? queueCounts.wallet_top_ups : 0;
                                return (
                                    <li key={item.id}>
                                        <Link
                                            href={item.href}
                                            onClick={() => onNavigate?.()}
                                            className={cn(
                                                'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                                active
                                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                            )}
                                        >
                                            <Icon className="h-4 w-4 shrink-0 opacity-80" />
                                            <span className="truncate">{item.label}</span>
                                            <QueueBadge count={count} />
                                        </Link>
                                    </li>
                                );
                            })}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

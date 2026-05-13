import { useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Sparkles } from 'lucide-react';
import { adminNavGroups } from '@/config/adminNav';
import { cn } from '@/lib/utils';
import { useAdminCan } from '@/hooks/useAdminCan';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

function QueueBadge({ count, compact = false }) {
    if (!count) return null;

    return (
        <span
            className={cn(
                'inline-flex min-w-6 items-center justify-center rounded-full bg-primary/12 px-2 py-0.5 text-[10px] font-semibold text-primary',
                compact && 'min-w-5 px-1.5',
            )}
        >
            {count > 99 ? '99+' : count}
        </span>
    );
}

function isItemActive(url, item) {
    if (item.href && (url === item.href || url.startsWith(`${item.href}/`))) {
        return true;
    }

    return Boolean(item.children?.some((child) => child.href && (url === child.href || url.startsWith(`${child.href}/`))));
}

function CollapsedTooltip({ enabled, label, children, submenu = [] }) {
    if (!enabled) {
        return children;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side="right" className="rounded-xl border border-border/80 bg-card px-3 py-2 text-left text-foreground shadow-card">
                <div className="min-w-[140px]">
                    <p className="text-[12px] font-semibold text-foreground">{label}</p>
                    {submenu.length > 0 ? (
                        <div className="mt-1.5 space-y-1">
                            {submenu.map((child) => (
                                <p key={child.id} className="text-[11px] font-medium text-muted-foreground">
                                    {child.label}
                                </p>
                            ))}
                        </div>
                    ) : null}
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

/**
 * @param {{ className?: string, onNavigate?: () => void, collapsed?: boolean, onToggleCollapse?: () => void, mobile?: boolean }} props
 */
export function SidebarNav({ className, onNavigate, collapsed = false, onToggleCollapse, mobile = false }) {
    const page = usePage();
    const url = page.url;
    const queueCounts = page.props.adminQueueCounts ?? {};
    const can = useAdminCan();

    const groups = useMemo(
        () =>
            adminNavGroups
                .map((group) => ({
                    ...group,
                    items: group.items
                        .map((item) => ({
                            ...item,
                            children: item.children?.filter((child) => !child.permission || can(child.permission)),
                        }))
                        .filter((item) => (!item.permission || can(item.permission)) && (!item.children || item.children.length > 0)),
                }))
                .filter((group) => group.items.length > 0),
        [can],
    );

    const [openSections, setOpenSections] = useState(() =>
        Object.fromEntries(
            groups.reduce((entries, group) => {
                const nextEntries = group.items
                    .filter((item) => item.children?.length)
                    .map((item) => [item.id, isItemActive(url, item)]);

                return entries.concat(nextEntries);
            }, []),
        ),
    );

    useEffect(() => {
        setOpenSections((current) => {
            const next = { ...current };
            groups.forEach((group) => {
                group.items.forEach((item) => {
                    if (item.children?.length && isItemActive(url, item)) {
                        next[item.id] = true;
                    }
                });
            });
            return next;
        });
    }, [groups, url]);

    return (
        <nav className={cn('flex h-full flex-col gap-3', className)}>
            <div className={cn('flex items-center justify-between gap-2 px-1', collapsed && !mobile && 'justify-center px-0')}>
                <div className={cn('flex items-center gap-2.5', collapsed && !mobile && 'justify-center')}>
                    <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-accent text-[rgb(132_112_255)] shadow-sm ring-1 ring-border/60 dark:bg-white/8 dark:ring-white/10">
                        <Sparkles className="h-4 w-4" />
                    </div>
                    <div className={cn('min-w-0 transition-all duration-200', collapsed && !mobile && 'hidden')}>
                        <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-muted-foreground dark:text-white/45">Sellova</p>
                        <p className="text-[15px] font-semibold tracking-tight text-foreground dark:text-white">Control Center</p>
                    </div>
                </div>
                {!mobile ? (
                    <Button type="button" variant="ghost" size="icon" className="h-7.5 w-7.5 rounded-lg text-muted-foreground hover:bg-accent/70 hover:text-foreground dark:text-white/60 dark:hover:bg-white/5 dark:hover:text-white" onClick={onToggleCollapse} aria-label="Toggle sidebar">
                        <ChevronRight className={cn('h-3.5 w-3.5 transition-transform duration-200', !collapsed && 'rotate-180')} />
                    </Button>
                ) : null}
            </div>

            <div className={cn('admin-scrollbar flex-1 space-y-3 overflow-y-auto pr-1', collapsed && !mobile && 'space-y-2 pr-0')}>
                {groups.map((group) => (
                    <div key={group.id}>
                        {group.label && !(collapsed && !mobile) ? (
                            <div className={cn('mb-1 px-2.5', collapsed && !mobile && 'px-0 text-center')}>
                                <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground/80 dark:text-white/38">
                                    {group.label}
                                </p>
                            </div>
                        ) : null}
                        <ul className="space-y-0.5">
                            {group.items.map((item) => {
                                const active = isItemActive(url, item);
                                const Icon = item.icon;
                                const hasChildren = Boolean(item.children?.length);
                                const count = item.badgeKey ? queueCounts[item.badgeKey] : 0;
                                const isOpen = openSections[item.id];
                                const collapsedRail = collapsed && !mobile;

                                if (hasChildren) {
                                    if (collapsedRail) {
                                        return (
                                            <li key={item.id}>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <button
                                                            type="button"
                                                            className={cn(
                                                                'group flex w-full items-center justify-center rounded-xl px-0 py-1.5 text-sm font-medium transition-all duration-200',
                                                                active
                                                                    ? 'sidebar-active-item'
                                                                    : 'text-sidebar-foreground hover:bg-accent/55 hover:text-foreground dark:hover:bg-white/5 dark:hover:text-white',
                                                            )}
                                                        >
                                                            <span className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-xl transition-colors', active ? 'sidebar-active-icon' : 'text-muted-foreground group-hover:bg-background/70 group-hover:text-foreground dark:text-white/52 dark:group-hover:bg-white/5 dark:group-hover:text-white')}>
                                                                <Icon className="h-3.5 w-3.5" />
                                                            </span>
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent side="right" align="start" className="w-56 p-2">
                                                        <DropdownMenuLabel className="text-[12px]">{item.label}</DropdownMenuLabel>
                                                        <div className="space-y-0.5">
                                                            {item.children.map((child) => {
                                                                const childActive = isItemActive(url, child);
                                                                const ChildIcon = child.icon;
                                                                const childCount = child.badgeKey ? queueCounts[child.badgeKey] : 0;

                                                                return (
                                                                    <Link
                                                                        key={child.id}
                                                                        href={child.href}
                                                                        onClick={() => onNavigate?.()}
                                                                        className={cn(
                                                                            'flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                                                                            childActive
                                                                                ? 'sidebar-submenu-active'
                                                                                : 'text-muted-foreground hover:bg-accent/55 hover:text-foreground dark:text-white/62 dark:hover:bg-white/5 dark:hover:text-white',
                                                                        )}
                                                                    >
                                                                        <ChildIcon className={cn('h-3.5 w-3.5 shrink-0', childActive ? 'text-[rgb(132_112_255)]' : 'text-muted-foreground/70 dark:text-white/42')} />
                                                                        <span className="flex-1 truncate">{child.label}</span>
                                                                        <QueueBadge count={childCount} />
                                                                    </Link>
                                                                );
                                                            })}
                                                        </div>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </li>
                                        );
                                    }

                                    return (
                                        <li key={item.id}>
                                            <CollapsedTooltip enabled={collapsedRail} label={item.label} submenu={item.children}>
                                                <div
                                                    className={cn(
                                                        'rounded-2xl transition-all duration-200',
                                                        active
                                                            ? 'sidebar-active-item'
                                                            : 'text-sidebar-foreground hover:bg-accent/55 hover:text-foreground dark:hover:bg-white/5 dark:hover:text-white',
                                                    )}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => setOpenSections((current) => ({ ...current, [item.id]: !current[item.id] }))}
                                                        className={cn(
                                                        'group flex w-full items-center gap-2 rounded-2xl px-2.5 py-1 text-left text-sm font-medium transition-all duration-200',
                                                        collapsedRail && 'justify-center rounded-xl px-0 py-1.5',
                                                    )}
                                                >
                                                        <span className={cn('flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors', collapsedRail && 'h-8 w-8 rounded-xl', active ? 'sidebar-active-icon' : 'text-muted-foreground group-hover:bg-background/70 group-hover:text-foreground dark:text-white/52 dark:group-hover:bg-white/5 dark:group-hover:text-white')}>
                                                            <Icon className="h-3.5 w-3.5" />
                                                        </span>
                                                        <span className={cn('min-w-0 flex-1 truncate', collapsedRail && 'hidden')}>{item.label}</span>
                                                        <span className={cn('flex items-center gap-2', collapsedRail && 'hidden')}>
                                                            <QueueBadge count={count} />
                                                            <ChevronDown className={cn('h-3 w-3 transition-transform duration-200', isOpen && 'rotate-180')} />
                                                        </span>
                                                    </button>
                                                    <div
                                                        className={cn(
                                                            'grid transition-all duration-200',
                                                            isOpen && !(collapsed && !mobile) ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0',
                                                        )}
                                                    >
                                                        <div className="overflow-hidden">
                                                            <ul className="space-y-0.5 px-3 pb-2 pl-[1.9rem] pr-2">
                                                            {item.children.map((child) => {
                                                                const childActive = isItemActive(url, child);
                                                                const ChildIcon = child.icon;
                                                                const childCount = child.badgeKey ? queueCounts[child.badgeKey] : 0;
                                                                return (
                                                                    <li key={child.id} className="mb-1 last:mb-0">
                                                                        <Link
                                                                            href={child.href}
                                                                            onClick={() => onNavigate?.()}
                                                                            className={cn(
                                                                                'group flex items-center gap-2 rounded-lg px-0 py-1 text-sm font-medium leading-[1.5715] transition-all duration-200',
                                                                                childActive
                                                                                    ? 'sidebar-submenu-active font-semibold'
                                                                                    : 'text-muted-foreground hover:text-foreground dark:text-white/62 dark:hover:text-white',
                                                                            )}
                                                                        >
                                                                            <ChildIcon className={cn('h-3 w-3 shrink-0', childActive ? 'text-[rgb(132_112_255)]' : 'text-muted-foreground/70 group-hover:text-foreground dark:text-white/40 dark:group-hover:text-white')} />
                                                                            <span className="truncate">{child.label}</span>
                                                                            <QueueBadge count={childCount} />
                                                                        </Link>
                                                                    </li>
                                                                );
                                                            })}
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </CollapsedTooltip>
                                        </li>
                                    );
                                }

                                return (
                                    <li key={item.id}>
                                        <CollapsedTooltip enabled={collapsed && !mobile} label={item.label}>
                                            <Link
                                                href={item.href}
                                                onClick={() => onNavigate?.()}
                                                className={cn(
                                                    'group flex items-center gap-2 rounded-xl px-2.5 py-1.5 text-sm font-medium transition-all duration-200',
                                                    active
                                                        ? 'sidebar-active-item'
                                                        : 'text-sidebar-foreground hover:bg-accent/55 hover:text-foreground dark:hover:bg-white/5 dark:hover:text-white',
                                                    collapsed && !mobile && 'justify-center rounded-xl px-0 py-1.5 shadow-none',
                                                )}
                                            >
                                                <span className={cn('flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-transparent transition-colors', collapsed && !mobile && 'h-8 w-8 rounded-xl border-transparent', active ? 'sidebar-active-icon' : 'bg-transparent text-muted-foreground group-hover:border-border/60 group-hover:bg-background/75 group-hover:text-foreground dark:text-white/52 dark:group-hover:border-white/10 dark:group-hover:bg-white/5 dark:group-hover:text-white')}>
                                                    <Icon className="h-3.5 w-3.5" />
                                                </span>
                                                <span className={cn('min-w-0 flex-1 truncate', collapsed && !mobile && 'hidden')}>{item.label}</span>
                                                <QueueBadge count={count} compact={collapsed && !mobile} />
                                            </Link>
                                        </CollapsedTooltip>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </div>
        </nav>
    );
}

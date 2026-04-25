import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * @param {{
 *   className?: string,
 *   children?: import('react').ReactNode,
 *   baseUrl: string,
 *   filters?: Record<string, string>,
 *   searchKeys?: string[],
 * }} props
 */
export function AdminFilterBar({ className, children, baseUrl, filters = {}, searchKeys = ['q'] }) {
    const primaryKey = searchKeys[0] ?? 'q';
    const [q, setQ] = useState(filters[primaryKey] ?? '');

    const apply = (e) => {
        e.preventDefault();
        const next = { ...filters, [primaryKey]: q, page: '1' };
        router.get(baseUrl, next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const clearAll = () => {
        router.get(baseUrl, { page: '1' }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const chips = Object.entries(filters).filter(([k, v]) => k !== 'page' && v !== undefined && v !== null && String(v) !== '');

    return (
        <form
            onSubmit={apply}
            className={cn(
                'flex flex-col gap-3 rounded-lg border border-border/80 bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="relative flex-1 sm:max-w-xs">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search…"
                    className="pl-9"
                    name={primaryKey}
                />
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" variant="secondary" size="sm">
                    Apply
                </Button>
                {chips.length > 0 && (
                    <Button type="button" variant="ghost" size="sm" onClick={clearAll}>
                        Clear filters
                    </Button>
                )}
                {children}
            </div>
            {chips.length > 0 && (
                <div className="flex flex-wrap gap-2 sm:ml-2">
                    {chips.map(([k, v]) => (
                        <span
                            key={k}
                            className="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                        >
                            {k}: {String(v)}
                        </span>
                    ))}
                </div>
            )}
        </form>
    );
}

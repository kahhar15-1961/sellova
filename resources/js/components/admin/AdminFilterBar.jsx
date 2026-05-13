import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
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
                'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="relative w-full sm:max-w-[448px]">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                <input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search records..."
                    className="h-9 w-full rounded-md border border-slate-200 bg-slate-50/70 pl-10 pr-3 text-[13px] font-medium text-slate-700 shadow-none outline-none transition placeholder:text-slate-400 focus:border-slate-300 focus:bg-white focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-slate-600 dark:focus:bg-slate-900"
                    name={primaryKey}
                />
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Button type="submit" variant="outline" size="sm" className="h-9 rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                    Apply
                </Button>
                {chips.length > 0 && (
                    <Button type="button" variant="ghost" size="sm" className="h-9 rounded-md px-3 text-[13px] font-semibold" onClick={clearAll}>
                        Clear filters
                    </Button>
                )}
                {children}
            </div>
            {chips.length > 0 && (
                <div className="flex flex-wrap gap-2 sm:basis-full">
                    {chips.map(([k, v]) => (
                        <span
                            key={k}
                            className="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                        >
                            {k}: {String(v)}
                        </span>
                    ))}
                </div>
            )}
        </form>
    );
}

import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * @param {{ baseUrl: string, pagination: { page: number, perPage: number, total: number, lastPage: number }, extraParams?: Record<string, string|number|undefined> }} props
 */
export function AdminPagination({ baseUrl, pagination, extraParams = {} }) {
    const page = Number(pagination?.page ?? 1);
    const perPage = Number(pagination?.perPage ?? 10);
    const total = Number(pagination?.total ?? 0);
    const lastPage = Number(pagination?.lastPage ?? 1);
    const from = total === 0 ? 0 : (page - 1) * perPage + 1;
    const to = Math.min(page * perPage, total);

    const go = (p) => {
        router.get(
            baseUrl,
            { ...extraParams, page: p },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className={cn('flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between')}>
            <p className="text-[13px] text-slate-500 dark:text-slate-400">
                Showing {from} to {to} of {total} results
            </p>
            <div className="flex items-center gap-2">
                <Button type="button" variant="outline" size="sm" className="h-9 rounded-lg border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" disabled={page <= 1} onClick={() => go(page - 1)}>
                    <ChevronLeft className="h-4 w-4" />
                    Previous
                </Button>
                <Button type="button" variant="outline" size="sm" className="h-9 rounded-lg border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" disabled={page >= lastPage} onClick={() => go(page + 1)}>
                    Next
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}

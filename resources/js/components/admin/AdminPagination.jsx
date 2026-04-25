import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * @param {{ baseUrl: string, pagination: { page: number, perPage: number, total: number, lastPage: number }, extraParams?: Record<string, string|number|undefined> }} props
 */
export function AdminPagination({ baseUrl, pagination, extraParams = {} }) {
    const { page, lastPage, total, perPage } = pagination;
    if (lastPage <= 1) {
        return <p className="text-xs text-muted-foreground">{total} record{total === 1 ? '' : 's'}</p>;
    }

    const go = (p) => {
        router.get(
            baseUrl,
            { ...extraParams, page: p },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className={cn('flex flex-wrap items-center justify-between gap-3')}>
            <p className="text-xs text-muted-foreground">
                Page {page} of {lastPage} · {total} total · {perPage} per page
            </p>
            <div className="flex gap-2">
                <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => go(page - 1)}>
                    Previous
                </Button>
                <Button type="button" variant="outline" size="sm" disabled={page >= lastPage} onClick={() => go(page + 1)}>
                    Next
                </Button>
            </div>
        </div>
    );
}

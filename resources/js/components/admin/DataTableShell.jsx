import { Link } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { EmptyState } from '@/components/admin/EmptyState';
import { DataTable, TableContainer, TableHead } from '@/components/admin/data-table';
import { cn } from '@/lib/utils';

/**
 * @param {{ columns: string[], rows: Record<string, unknown>[], emptyTitle?: string, emptyDescription?: string, className?: string, linkableFirstColumn?: boolean, dense?: boolean, stickyHeader?: boolean, renderers?: Record<string, (value: unknown, row: Record<string, unknown>) => import('react').ReactNode> }} props
 */
export function DataTableShell({
    columns,
    rows,
    emptyTitle = 'No records',
    emptyDescription,
    className,
    linkableFirstColumn = false,
    dense = false,
    stickyHeader = true,
    renderers = {},
}) {
    if (!rows || rows.length === 0) {
        return (
            <EmptyState
                title={emptyTitle}
                description={emptyDescription ?? 'Data will appear here once this module is connected to the API.'}
                icon={<Package className="h-6 w-6" />}
            />
        );
    }

    return (
        <DataTable className={className}>
            <div className="admin-scrollbar overflow-x-auto">
                <TableContainer className="rounded-none border-0 bg-transparent shadow-none">
                    <thead className={cn(stickyHeader && 'sticky top-0 z-10')}>
                        <tr className="h-[38px] border-b border-slate-200 bg-slate-50/80 hover:bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/30 dark:hover:bg-slate-900/30">
                            {columns.map((col) => (
                                <TableHead key={col} className="px-3 text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">{col}</TableHead>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, ri) => {
                            const href = typeof row.href === 'string' ? row.href : null;
                            return (
                                <tr key={ri} className="h-[49px] border-b border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-700/70 dark:hover:bg-slate-900/22">
                                    {columns.map((col, ci) => {
                                        const raw = row[col] ?? '—';
                                        const rendered = renderers[col] ? renderers[col](raw, row) : String(raw);
                                        if (linkableFirstColumn && ci === 0 && href) {
                                            return (
                                                <td key={col} className={cn('px-3 align-middle text-[12px]', dense ? 'py-2' : 'py-3')}>
                                                    <Link href={href} className="font-semibold text-slate-950 transition-colors hover:text-primary dark:text-slate-100">
                                                        {rendered}
                                                    </Link>
                                                </td>
                                            );
                                        }
                                        return (
                                            <td key={col} className={cn('px-3 align-middle text-[12px] text-slate-700 dark:text-slate-300', dense ? 'py-2' : 'py-3')}>
                                                {rendered}
                                            </td>
                                        );
                                    })}
                                </tr>
                            );
                        })}
                    </tbody>
                </TableContainer>
            </div>
        </DataTable>
    );
}

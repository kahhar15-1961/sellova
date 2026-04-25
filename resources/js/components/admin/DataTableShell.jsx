import { Link } from '@inertiajs/react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/admin/EmptyState';
import { Package } from 'lucide-react';
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
        <div className={cn('overflow-x-auto rounded-lg border border-border/80 bg-card shadow-sm', className)}>
            <Table>
                <TableHeader className={cn(stickyHeader && 'sticky top-0 z-10 bg-card')}>
                    <TableRow>
                        {columns.map((col) => (
                            <TableHead key={col}>{col}</TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row, ri) => {
                        const href = typeof row.href === 'string' ? row.href : null;
                        return (
                            <TableRow key={ri} className="hover:bg-muted/40">
                                {columns.map((col, ci) => {
                                    const raw = row[col] ?? '—';
                                    const rendered = renderers[col] ? renderers[col](raw, row) : String(raw);
                                    if (linkableFirstColumn && ci === 0 && href) {
                                        return (
                                            <TableCell key={col} className={cn(dense && 'py-2')}>
                                                <Link href={href} className="font-medium text-primary hover:underline">
                                                    {rendered}
                                                </Link>
                                            </TableCell>
                                        );
                                    }
                                    return <TableCell key={col} className={cn(dense && 'py-2')}>{rendered}</TableCell>;
                                })}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

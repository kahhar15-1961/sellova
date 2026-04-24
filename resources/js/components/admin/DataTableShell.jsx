import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/admin/EmptyState';
import { Package } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * @param {{ columns: string[], rows: Record<string, unknown>[], emptyTitle?: string, emptyDescription?: string, className?: string }} props
 */
export function DataTableShell({ columns, rows, emptyTitle = 'No records', emptyDescription, className }) {
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
        <div className={cn('rounded-lg border border-border/80 bg-card shadow-sm', className)}>
            <Table>
                <TableHeader>
                    <TableRow>
                        {columns.map((col) => (
                            <TableHead key={col}>{col}</TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row, ri) => (
                        <TableRow key={ri}>
                            {columns.map((col) => (
                                <TableCell key={col}>{String(row[col] ?? '—')}</TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

import { Search, SlidersHorizontal } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Table as PrimitiveTable,
    TableBody,
    TableCell,
    TableHead as PrimitiveTableHead,
    TableHeader as PrimitiveTableHeader,
    TableRow as PrimitiveTableRow,
} from '@/components/ui/table';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { AdminPagination } from '@/components/admin/AdminPagination';

export function DataTable({ className, children }) {
    return <div className={cn('ds-table-shell', className)}>{children}</div>;
}

export function TableHeader({ title = 'Records', description, actions, className }) {
    return (
        <div className={cn('ds-table-toolbar', className)}>
            <div className="space-y-1">
                <h3 className="font-display text-lg font-semibold tracking-[-0.02em] text-foreground">{title}</h3>
                {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
            </div>
            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}

export function TableSearch({ value, onChange, placeholder = 'Search records', className }) {
    return (
        <div className={cn('relative min-w-[240px]', className)}>
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground/70" />
            <Input value={value} onChange={onChange} placeholder={placeholder} className="pl-10" />
        </div>
    );
}

export function TableFilter({ children, className, label = 'Filters' }) {
    return (
        <div
            className={cn(
                'inline-flex min-h-11 items-center gap-2 rounded-2xl border border-input/85 bg-card px-4 text-[13px] font-medium text-foreground shadow-sm',
                className,
            )}
        >
            <SlidersHorizontal className="h-4 w-4 text-muted-foreground" />
            <span>{label}</span>
            {children}
        </div>
    );
}

export function BulkActionBar({ selectedCount = 0, actions, className }) {
    if (!selectedCount) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex flex-col gap-3 border-b border-border/70 bg-accent/50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <p className="text-sm font-medium text-foreground">
                {selectedCount} item{selectedCount === 1 ? '' : 's'} selected
            </p>
            <div className="flex flex-wrap gap-2">{actions}</div>
        </div>
    );
}

export function TableContainer({ className, ...props }) {
    return <PrimitiveTable className={cn('rounded-none border-0 bg-transparent shadow-none', className)} {...props} />;
}

export function TableHead(props) {
    return <PrimitiveTableHead {...props} />;
}

export function TableRow(props) {
    return <PrimitiveTableRow {...props} />;
}

export function TableMarkup({ columns, rows, renderers = {}, className }) {
    return (
        <div className="admin-scrollbar overflow-x-auto">
            <TableContainer className={className}>
                <PrimitiveTableHeader className="sticky top-0 z-10">
                    <PrimitiveTableRow className="h-auto hover:bg-transparent">
                        {columns.map((column) => (
                            <PrimitiveTableHead key={column}>{column}</PrimitiveTableHead>
                        ))}
                    </PrimitiveTableRow>
                </PrimitiveTableHeader>
                <TableBody>
                    {rows.map((row, index) => (
                        <PrimitiveTableRow key={row.id ?? index}>
                            {columns.map((column) => (
                                <TableCell key={column}>
                                    {renderers[column] ? renderers[column](row[column], row) : row[column]}
                                </TableCell>
                            ))}
                        </PrimitiveTableRow>
                    ))}
                </TableBody>
            </TableContainer>
        </div>
    );
}

export function Pagination(props) {
    return <AdminPagination {...props} />;
}

export function TableActions({ className, children }) {
    return <div className={cn('flex flex-wrap items-center gap-2', className)}>{children}</div>;
}

export function SecondaryTableButton(props) {
    return <Button variant="outline" size="sm" {...props} />;
}

export { StatusBadge };

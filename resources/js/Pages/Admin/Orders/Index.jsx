import { Head, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { StatCard } from '@/components/admin/StatCard';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

function fmtDate(iso) {
    if (!iso || iso === '—') return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

export default function OrdersIndex({ header, rows, pagination, filters, index_url, status_options, summary }) {
    const f = filters || {};
    const status = f.status ?? '';

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Total orders" value={String(summary?.total ?? 0)} />
                    <StatCard label="In escrow" value={String(summary?.in_escrow ?? 0)} />
                    <StatCard label="Open disputes" value={String(summary?.open_disputes ?? 0)} />
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <AdminFilterBar baseUrl={index_url} filters={f} />
                    </div>
                    <div className="w-full sm:w-56">
                        <p className="mb-1 text-xs font-medium text-muted-foreground">Status</p>
                        <Select
                            value={status || 'all'}
                            onValueChange={(v) => {
                                const next = { ...f, page: '1' };
                                if (v === 'all') {
                                    delete next.status;
                                } else {
                                    next.status = v;
                                }
                                router.get(index_url, next, { preserveState: true, replace: true });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {(status_options || []).map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DataTableShell
                    columns={['order', 'buyer', 'total', 'status', 'placed']}
                    rows={rows}
                    emptyTitle="No orders match"
                    emptyDescription="Try clearing filters or seeding data."
                    linkableFirstColumn
                    renderers={{
                        status: (value) => <StatusBadge status={String(value)} />,
                        placed: (value) => <span className="text-muted-foreground">{fmtDate(value)}</span>,
                    }}
                />
                <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
            </div>
        </AdminLayout>
    );
}

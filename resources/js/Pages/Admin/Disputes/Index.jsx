import { Head, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { StatCard } from '@/components/admin/StatCard';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function DisputesIndex({ header, rows, pagination, filters, index_url, status_options, summary }) {
    const f = filters || {};
    const status = f.status ?? '';

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Opened" value={String(summary?.opened ?? 0)} />
                    <StatCard label="Under review" value={String(summary?.under_review ?? 0)} />
                    <StatCard label="Resolved" value={String(summary?.resolved ?? 0)} />
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
                                if (v === 'all') delete next.status;
                                else next.status = v;
                                router.get(index_url, next, { preserveState: true, replace: true });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All" />
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
                    columns={['case', 'order', 'stage']}
                    rows={rows}
                    emptyTitle="No disputes"
                    linkableFirstColumn
                    renderers={{
                        stage: (value) => <StatusBadge status={String(value)} />,
                    }}
                />
                <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
            </div>
        </AdminLayout>
    );
}

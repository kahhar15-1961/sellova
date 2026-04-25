import { Head, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function EscrowsIndex({ header, rows, pagination, filters, index_url, state_options }) {
    const f = filters || {};
    const state = f.state ?? '';

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <AdminFilterBar baseUrl={index_url} filters={f} />
                    </div>
                    <div className="w-full sm:w-56">
                        <p className="mb-1 text-xs font-medium text-muted-foreground">Escrow state</p>
                        <Select
                            value={state || 'all'}
                            onValueChange={(v) => {
                                const next = { ...f, page: '1' };
                                if (v === 'all') delete next.state;
                                else next.state = v;
                                router.get(index_url, next, { preserveState: true, replace: true });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All states</SelectItem>
                                {(state_options || []).map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DataTableShell columns={['order', 'state', 'held']} rows={rows} emptyTitle="No escrow accounts" />
                <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
            </div>
        </AdminLayout>
    );
}

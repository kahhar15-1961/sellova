import { Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { FilterBar } from '@/components/admin/FilterBar';
import { DataTableShell } from '@/components/admin/DataTableShell';

export default function OrdersIndex({ header, rows, pagination }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <FilterBar />
                <DataTableShell columns={['order', 'buyer', 'status']} rows={rows} emptyTitle="No orders loaded" />
                <p className="text-xs text-muted-foreground">
                    Pagination: page {pagination.page} · {pagination.total} total
                </p>
            </div>
        </AdminLayout>
    );
}

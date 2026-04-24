import { Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { FilterBar } from '@/components/admin/FilterBar';
import { DataTableShell } from '@/components/admin/DataTableShell';

export default function UsersIndex({ header, rows, pagination }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <FilterBar />
                <DataTableShell columns={['id', 'email', 'status']} rows={rows} emptyTitle="No users loaded" />
                <p className="text-xs text-muted-foreground">
                    Pagination placeholder: page {pagination.page} · {pagination.perPage} per page · {pagination.total} total
                </p>
            </div>
        </AdminLayout>
    );
}

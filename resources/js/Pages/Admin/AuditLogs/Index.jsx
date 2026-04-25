import { Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { StatCard } from '@/components/admin/StatCard';
import { Button } from '@/components/ui/button';

function fmtDate(iso) {
    if (!iso || iso === '—') return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

export default function AuditLogsIndex({ header, rows, pagination, filters, index_url, summary, export_url }) {
    const f = filters || {};
    const qs = new URLSearchParams();
    Object.entries(f).forEach(([k, v]) => {
        if (v !== undefined && v !== null && String(v) !== '') qs.set(k, String(v));
    });
    const downloadHref = qs.toString() ? `${export_url}?${qs.toString()}` : export_url;

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader
                title={header.title}
                description={header.description}
                breadcrumbs={header.breadcrumbs}
                actions={
                    <Button asChild variant="outline" size="sm">
                        <a href={downloadHref}>Export CSV</a>
                    </Button>
                }
            />
            <div className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Total logs" value={String(summary?.total ?? 0)} />
                    <StatCard label="Today" value={String(summary?.today ?? 0)} />
                    <StatCard label="Admin actions" value={String(summary?.admin_actions ?? 0)} />
                </div>
                <AdminFilterBar baseUrl={index_url} filters={f} />
                <DataTableShell
                    columns={['time', 'actor', 'action', 'target', 'reason']}
                    rows={rows}
                    emptyTitle="No audit entries"
                    linkableFirstColumn
                    renderers={{
                        time: (value) => <span className="text-muted-foreground">{fmtDate(value)}</span>,
                    }}
                />
                <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
            </div>
        </AdminLayout>
    );
}

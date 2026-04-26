import { Head, Link, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { StatCard } from '@/components/admin/StatCard';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function SellerProfilesIndex({ header, rows, pagination, filters, index_url, verification_options, summary }) {
    const f = filters || {};
    const verification = f.verification ?? '';

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Total sellers" value={String(summary?.total ?? 0)} />
                    <StatCard label="Verified" value={String(summary?.verified ?? 0)} />
                    <StatCard label="Pending verification" value={String(summary?.pending ?? 0)} />
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1"><AdminFilterBar baseUrl={index_url} filters={f} /></div>
                    <div className="w-full sm:w-64">
                        <p className="mb-1 text-xs font-medium text-muted-foreground">Verification</p>
                        <Select
                            value={verification || 'all'}
                            onValueChange={(v) => {
                                const next = { ...f, page: '1' };
                                if (v === 'all') delete next.verification;
                                else next.verification = v;
                                router.get(index_url, next, { preserveState: true, replace: true });
                            }}
                        >
                            <SelectTrigger><SelectValue placeholder="All" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All verification states</SelectItem>
                                {(verification_options || []).map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DataTableShell
                    columns={['seller', 'display_name', 'account', 'verification', 'store', 'products', 'pending_withdrawals']}
                    rows={rows}
                    emptyTitle="No sellers"
                    renderers={{
                        seller: (value, row) => <Link href={row.href} className="font-medium text-primary hover:underline">{String(value)}</Link>,
                        verification: (value) => <StatusBadge status={String(value)} />,
                        store: (value) => <StatusBadge status={String(value)} />,
                    }}
                />
                <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
            </div>
        </AdminLayout>
    );
}

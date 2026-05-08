import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { StatCard } from '@/components/admin/StatCard';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

function fmtDate(iso) {
    if (!iso || iso === '—') return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

export default function UsersIndex({ header, rows, pagination, filters, index_url, status_options, summary, bulk_update_url }) {
    const f = filters || {};
    const status = f.status ?? '';
    const [selected, setSelected] = useState(() => new Set());
    const [bulkStatus, setBulkStatus] = useState('active');
    const [bulkRisk, setBulkRisk] = useState('');
    const [selectAllFiltered, setSelectAllFiltered] = useState(false);
    const visibleIds = useMemo(() => rows.map((r) => Number(r.row_id)).filter(Boolean), [rows]);

    const allChecked = visibleIds.length > 0 && visibleIds.every((id) => selected.has(id));

    const toggleAll = (checked) => {
        const next = new Set(selected);
        visibleIds.forEach((id) => {
            if (checked) next.add(id);
            else next.delete(id);
        });
        setSelected(next);
    };

    const toggleOne = (id, checked) => {
        const next = new Set(selected);
        if (checked) next.add(id);
        else next.delete(id);
        setSelected(next);
    };

    const applyBulk = () => {
        const ids = Array.from(selected);
        if (!ids.length && !selectAllFiltered) return;
        const scopeLabel = selectAllFiltered ? 'all filtered users' : `${ids.length} selected users`;
        if (!window.confirm(`Apply bulk update to ${scopeLabel}?`)) return;
        router.post(
            bulk_update_url,
            {
                ids,
                select_all: selectAllFiltered,
                filters: { q: f.q ?? '', status: f.status ?? '' },
                status: bulkStatus,
                risk_level: bulkRisk || undefined,
                reason: 'bulk_admin_update',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected(new Set());
                    setSelectAllFiltered(false);
                },
            },
        );
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-4">
                    <StatCard label="Total users" value={String(summary?.total ?? 0)} />
                    <StatCard label="Active" value={String(summary?.active ?? 0)} />
                    <StatCard label="Suspended" value={String(summary?.suspended ?? 0)} />
                    <StatCard label="Staff" value={String(summary?.staff ?? 0)} />
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

                <div className="rounded-lg border border-border/70 bg-card p-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="button" size="sm" variant="outline" onClick={() => toggleAll(!allChecked)}>
                            {allChecked ? 'Unselect page' : 'Select page'}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={selectAllFiltered ? 'default' : 'outline'}
                            onClick={() => setSelectAllFiltered((v) => !v)}
                        >
                            {selectAllFiltered ? 'All filtered selected' : 'Select all filtered'}
                        </Button>
                        <select value={bulkStatus} onChange={(e) => setBulkStatus(e.target.value)} className="h-9 rounded-md border px-2 text-sm">
                            <option value="active">active</option>
                            <option value="suspended">suspended</option>
                            <option value="closed">closed</option>
                        </select>
                        <select value={bulkRisk} onChange={(e) => setBulkRisk(e.target.value)} className="h-9 rounded-md border px-2 text-sm">
                            <option value="">keep risk</option>
                            <option value="low">low</option>
                            <option value="medium">medium</option>
                            <option value="high">high</option>
                        </select>
                        <Button type="button" size="sm" onClick={applyBulk} disabled={selected.size === 0 && !selectAllFiltered}>
                            {selectAllFiltered ? 'Apply to all filtered' : `Apply to ${selected.size} selected`}
                        </Button>
                    </div>
                </div>

                <DataTableShell
                    columns={['select', 'id', 'email', 'buyer_profile', 'seller_profile', 'status', 'risk', 'roles', 'last_login']}
                    rows={rows.map((r) => ({ ...r, select: String(r.row_id ?? '') }))}
                    emptyTitle="No users"
                    renderers={{
                        select: (_value, row) => {
                            const id = Number(row.row_id);
                            return (
                                <input
                                    type="checkbox"
                                    checked={selected.has(id)}
                                    onChange={(e) => toggleOne(id, e.target.checked)}
                                />
                            );
                        },
                        id: (value, row) => (
                            <Link href={row.href} className="font-medium text-primary hover:underline">
                                {String(value)}
                            </Link>
                        ),
                        buyer_profile: (value, row) => (
                            row.buyer_href ? (
                                <Link href={row.buyer_href} className="text-primary hover:underline">
                                    {String(value)}
                                </Link>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )
                        ),
                        seller_profile: (value, row) => (
                            row.seller_href ? (
                                <Link href={row.seller_href} className="text-primary hover:underline">
                                    {String(value)}
                                </Link>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )
                        ),
                        status: (value) => <StatusBadge status={String(value)} />,
                        last_login: (value) => <span className="text-muted-foreground">{fmtDate(value)}</span>,
                    }}
                />
                <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
            </div>
        </AdminLayout>
    );
}

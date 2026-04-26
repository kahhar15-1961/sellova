import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatCard } from '@/components/admin/StatCard';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';

function fmtDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); } catch { return String(iso); }
}

export default function EscalationsIndex({ header, rows, summary, filters, index_url, action_url, staff_users: staffUsers = [] }) {
    const f = filters || {};
    const queueFilter = f.queue || 'all';
    const statusFilter = f.status || 'open';
    const [assigneeByIncident, setAssigneeByIncident] = useState({});
    const [resolutionByIncident, setResolutionByIncident] = useState({});

    const applyFilters = (next) => {
        const query = { ...f, ...next };
        if (!query.queue || query.queue === 'all') delete query.queue;
        if (!query.status || query.status === 'all') delete query.status;
        router.get(index_url, query, { preserveState: true, replace: true });
    };

    const assignIncident = (row) => {
        const selected = assigneeByIncident[row.id];
        const assigneeId = Number(selected ?? row.assignee_user_id ?? 0);
        if (!Number.isInteger(assigneeId) || assigneeId <= 0) return;
        router.post(
            action_url,
            { incident_id: row.id, action: 'reassign', assignee_user_id: assigneeId },
            { preserveScroll: true },
        );
    };

    const resolveIncident = (row) => {
        const reason = String(
            resolutionByIncident[row.id]
            ?? row.reason
            ?? 'resolved_from_inbox',
        ).trim();
        if (!reason) return;
        router.post(
            action_url,
            { incident_id: row.id, action: 'resolve', resolution_reason: reason },
            { preserveScroll: true },
        );
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-4">
                    <StatCard label="Open" value={String(summary?.open ?? 0)} />
                    <StatCard label="Acknowledged" value={String(summary?.acknowledged ?? 0)} />
                    <StatCard label="Resolved" value={String(summary?.resolved ?? 0)} />
                    <StatCard label="Critical active" value={String(summary?.critical ?? 0)} />
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <AdminFilterBar baseUrl={index_url} filters={f} />
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:w-[280px]">
                        <select
                            value={statusFilter}
                            className="h-9 rounded-md border px-2 text-sm"
                            onChange={(e) => applyFilters({ status: e.target.value })}
                        >
                            <option value="open">open</option>
                            <option value="acknowledged">acknowledged</option>
                            <option value="resolved">resolved</option>
                            <option value="all">all</option>
                        </select>
                        <select
                            value={queueFilter}
                            className="h-9 rounded-md border px-2 text-sm"
                            onChange={(e) => applyFilters({ queue: e.target.value })}
                        >
                            <option value="all">all queues</option>
                            <option value="disputes">disputes</option>
                            <option value="withdrawals">withdrawals</option>
                            <option value="approvals">approvals</option>
                        </select>
                    </div>
                </div>

                <DataTableShell
                    columns={['id', 'queue', 'target', 'severity', 'status', 'assignee', 'opened_at', 'actions']}
                    rows={rows}
                    emptyTitle="No escalation incidents"
                    renderers={{
                        severity: (v) => <StatusBadge status={String(v)} />,
                        status: (v) => <StatusBadge status={String(v)} />,
                        opened_at: (v) => <span className="text-muted-foreground">{fmtDate(v)}</span>,
                        actions: (_v, row) => (
                            <div className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={row.status !== 'open'}
                                    onClick={() => router.post(action_url, { incident_id: row.id, action: 'acknowledge' }, { preserveScroll: true })}
                                >
                                    Ack
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={row.status === 'resolved'}
                                    onClick={() => assignIncident(row)}
                                >
                                    Assign
                                </Button>
                                <Button
                                    size="sm"
                                    disabled={row.status === 'resolved'}
                                    onClick={() => resolveIncident(row)}
                                >
                                    Resolve
                                </Button>
                                <select
                                    className="h-8 rounded-md border px-2 text-xs"
                                    value={String(assigneeByIncident[row.id] ?? row.assignee_user_id ?? '')}
                                    onChange={(e) => setAssigneeByIncident((prev) => ({ ...prev, [row.id]: e.target.value }))}
                                    disabled={row.status === 'resolved'}
                                >
                                    <option value="">Select assignee</option>
                                    {(staffUsers || []).map((u) => (
                                        <option key={u.id} value={u.id}>{u.email}</option>
                                    ))}
                                </select>
                                <input
                                    className="h-8 min-w-[180px] rounded-md border px-2 text-xs"
                                    placeholder="Resolution reason"
                                    value={resolutionByIncident[row.id] ?? ''}
                                    onChange={(e) => setResolutionByIncident((prev) => ({ ...prev, [row.id]: e.target.value }))}
                                    disabled={row.status === 'resolved'}
                                />
                            </div>
                        ),
                    }}
                />
            </div>
        </AdminLayout>
    );
}

import { Head, router } from '@inertiajs/react';
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

export default function EscalationsIndex({ header, rows, summary, filters, index_url, action_url }) {
    const f = filters || {};

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

                <AdminFilterBar baseUrl={index_url} filters={f} />

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
                                    onClick={() => {
                                        const assignee = window.prompt('Assign user id', row.assignee_user_id || '');
                                        if (!assignee) return;
                                        router.post(action_url, { incident_id: row.id, action: 'reassign', assignee_user_id: Number(assignee) }, { preserveScroll: true });
                                    }}
                                >
                                    Assign
                                </Button>
                                <Button
                                    size="sm"
                                    disabled={row.status === 'resolved'}
                                    onClick={() => router.post(action_url, { incident_id: row.id, action: 'resolve', resolution_reason: 'resolved_from_inbox' }, { preserveScroll: true })}
                                >
                                    Resolve
                                </Button>
                            </div>
                        ),
                    }}
                />
            </div>
        </AdminLayout>
    );
}

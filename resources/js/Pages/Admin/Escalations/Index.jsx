import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarClock, ChevronDown, Download, Ellipsis, Filter, Search, ShieldAlert, Target, UserRound } from 'lucide-react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatCard } from '@/components/admin/StatCard';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return String(iso);
    }
}

function avatarTone(seed) {
    const tones = [
        'bg-amber-100 text-amber-700 dark:bg-amber-500/16 dark:text-amber-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/16 dark:text-violet-300',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/16 dark:text-emerald-300',
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/16 dark:text-indigo-300',
        'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/16 dark:text-cyan-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/16 dark:text-rose-300',
    ];
    const index = Math.abs(
        String(seed || '')
            .split('')
            .reduce((sum, char) => sum + char.charCodeAt(0), 0),
    ) % tones.length;
    return tones[index];
}

function initialForIncident(value) {
    const normalized = String(value || '').replace(/[^a-zA-Z0-9]/g, '').trim();
    return normalized ? normalized.charAt(0).toUpperCase() : 'E';
}

export default function EscalationsIndex({ header, rows, summary, filters, pagination, index_url, action_url, slo_export_url: sloExportUrl, staff_users: staffUsers = [] }) {
    const f = filters || {};
    const queueFilter = f.queue || 'all';
    const statusFilter = f.status || 'open';
    const [query, setQuery] = useState(f.q ?? '');
    const [selected, setSelected] = useState(() => new Set());
    const [assigneeByIncident, setAssigneeByIncident] = useState({});
    const [resolutionByIncident, setResolutionByIncident] = useState({});
    const allChecked = rows?.length > 0 && selected.size === rows.length;

    const applyFilters = (next) => {
        const params = { ...f, q: query, page: '1', ...next };
        if (!params.q) delete params.q;
        if (!params.queue || params.queue === 'all') delete params.queue;
        if (!params.status || params.status === 'all') delete params.status;
        router.get(index_url, params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleAll = (checked) => {
        setSelected(checked ? new Set((rows || []).map((row) => String(row.id))) : new Set());
    };

    const toggleOne = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            if (checked) next.add(id);
            else next.delete(id);
            return next;
        });
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
                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="MTTA (30d, min)" value={String(summary?.mtta_minutes ?? 0)} />
                    <StatCard label="MTTR (30d, min)" value={String(summary?.mttr_minutes ?? 0)} />
                    <StatCard label="Reopened (30d)" value={String(summary?.reopened_30d ?? 0)} />
                </div>
                <div className="flex justify-end">
                    <a href={sloExportUrl} className="inline-flex h-[42px] items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-[13px] font-semibold text-slate-700 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/50">
                        <Download className="h-4 w-4" />
                        Export SLO CSV
                    </a>
                </div>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-700 lg:flex-row lg:items-center lg:justify-between">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters({});
                            }}
                            className="w-full max-w-[448px]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                                <input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Search escalations by target or reason..."
                                    className="h-[42px] w-full rounded-md border border-slate-200 bg-slate-50/70 pl-10 pr-3 text-[13px] font-medium text-slate-700 shadow-none outline-none transition placeholder:text-slate-400 focus:border-slate-300 focus:bg-white focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-slate-600 dark:focus:bg-slate-900"
                                />
                            </div>
                        </form>

                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-3">
                            <Button type="button" variant="outline" size="sm" className="h-[42px] rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800" onClick={() => applyFilters({})}>
                                Apply
                            </Button>
                            <span className="hidden h-6 w-px bg-slate-200 dark:bg-slate-700 sm:block" />
                            <select
                                value={statusFilter}
                                className="h-[42px] w-[160px] min-w-[160px] shrink-0 rounded-md border border-slate-200 bg-white px-4 text-[13px] font-semibold text-slate-700 shadow-none outline-none transition focus:border-slate-300 focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                onChange={(e) => applyFilters({ status: e.target.value })}
                            >
                                <option value="open">open</option>
                                <option value="acknowledged">acknowledged</option>
                                <option value="resolved">resolved</option>
                                <option value="all">all</option>
                            </select>
                            <select
                                value={queueFilter}
                                className="h-[42px] w-[160px] min-w-[160px] shrink-0 rounded-md border border-slate-200 bg-white px-4 text-[13px] font-semibold text-slate-700 shadow-none outline-none transition focus:border-slate-300 focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                onChange={(e) => applyFilters({ queue: e.target.value })}
                            >
                                <option value="all">all queues</option>
                                <option value="disputes">disputes</option>
                                <option value="withdrawals">withdrawals</option>
                                <option value="approvals">approvals</option>
                            </select>
                            <Button type="button" variant="outline" size="sm" className="h-[42px] shrink-0 gap-2 rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                <Filter className="h-4 w-4" />
                                Filters
                            </Button>
                            <Button type="button" size="sm" className="h-[42px] shrink-0 rounded-md bg-slate-950 px-4 text-[13px] font-semibold text-white shadow-none hover:bg-slate-900 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white">
                                Bulk actions
                                <ChevronDown className="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div className="admin-scrollbar overflow-x-auto">
                        <table className="min-w-full border-collapse">
                            <thead>
                                <tr className="h-[45px] border-b border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/30">
                                    <th className="w-[40px] px-4 text-left">
                                        <input type="checkbox" className="users-table-checkbox" checked={allChecked} onChange={(event) => toggleAll(event.target.checked)} />
                                    </th>
                                    <th className="min-w-[140px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Incident</th>
                                    <th className="min-w-[130px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Queue</th>
                                    <th className="min-w-[240px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Target</th>
                                    <th className="min-w-[120px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Severity</th>
                                    <th className="min-w-[130px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Status</th>
                                    <th className="min-w-[230px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Assignee</th>
                                    <th className="min-w-[190px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Opened</th>
                                    <th className="min-w-[520px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Actions</th>
                                    <th className="w-12 px-4" />
                                </tr>
                            </thead>
                            <tbody>
                                {rows?.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                            No escalation incidents found for the current filters.
                                        </td>
                                    </tr>
                                ) : (
                                    (rows || []).map((row) => {
                                        const id = String(row.id);
                                        return (
                                            <tr
                                                key={id}
                                                className="min-h-[65px] border-b border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-700/70 dark:hover:bg-slate-900/22"
                                            >
                                                <td className="px-4 py-4 align-middle">
                                                    <input type="checkbox" className="users-table-checkbox" checked={selected.has(id)} onChange={(event) => toggleOne(id, event.target.checked)} />
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <div className="flex items-center gap-3">
                                                        <div className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold', avatarTone(id))}>
                                                            {initialForIncident(id)}
                                                        </div>
                                                        <Link href={String(row.href || '#')} className="block truncate text-[13px] font-semibold text-slate-950 transition-colors hover:text-primary dark:text-slate-100">
                                                            #{id}
                                                        </Link>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-4 align-middle text-[13px] font-semibold capitalize text-slate-700 dark:text-slate-300">
                                                    {String(row.queue ?? '—')}
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <Link href={String(row.href || '#')} className="inline-flex items-center gap-2 text-[13px] font-semibold text-slate-700 transition-colors hover:text-primary dark:text-slate-300">
                                                        <Target className="h-4 w-4 text-slate-400" />
                                                        {String(row.target ?? '—')}
                                                    </Link>
                                                    <div className="mt-1 text-[12px] text-slate-500 dark:text-slate-400">{String(row.reason ?? '—')}</div>
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <StatusBadge status={String(row.severity ?? '')} className="px-2.5 py-1 text-[10px]" />
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <StatusBadge status={String(row.status ?? '')} className="px-2.5 py-1 text-[10px]" />
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <span className={cn('inline-flex items-center gap-2 text-[13px] font-medium', row.assignee_user_id ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500')}>
                                                        <UserRound className="h-4 w-4 text-slate-400" />
                                                        {String(row.assignee ?? 'Unassigned')}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <span className="inline-flex items-center gap-2 text-[13px] font-medium text-slate-500 dark:text-slate-400">
                                                        <CalendarClock className="h-4 w-4 text-slate-400" />
                                                        {fmtDate(row.opened_at)}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 rounded-md px-3 text-[12px] font-semibold shadow-none"
                                                            disabled={row.status !== 'open'}
                                                            onClick={() => router.post(action_url, { incident_id: row.id, action: 'acknowledge' }, { preserveScroll: true })}
                                                        >
                                                            Ack
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 rounded-md px-3 text-[12px] font-semibold shadow-none"
                                                            disabled={row.status === 'resolved'}
                                                            onClick={() => assignIncident(row)}
                                                        >
                                                            Assign
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            className="h-8 rounded-md px-3 text-[12px] font-semibold shadow-none"
                                                            disabled={row.status === 'resolved'}
                                                            onClick={() => resolveIncident(row)}
                                                        >
                                                            Resolve
                                                        </Button>
                                                        <select
                                                            className="h-8 min-w-[180px] rounded-md border border-slate-200 bg-white px-2 text-[12px] font-medium text-slate-700 shadow-none outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
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
                                                            className="h-8 min-w-[180px] rounded-md border border-slate-200 bg-white px-2 text-[12px] font-medium text-slate-700 shadow-none outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                            placeholder="Resolution reason"
                                                            value={resolutionByIncident[row.id] ?? ''}
                                                            onChange={(e) => setResolutionByIncident((prev) => ({ ...prev, [row.id]: e.target.value }))}
                                                            disabled={row.status === 'resolved'}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-right align-middle">
                                                    <Button type="button" variant="ghost" size="sm" className="h-7 w-7 rounded-md p-0 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                                                        <Ellipsis className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                    <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
                </section>
            </div>
        </AdminLayout>
    );
}

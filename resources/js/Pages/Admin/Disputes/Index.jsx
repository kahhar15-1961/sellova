import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronDown, Clock, Filter, Search, ShieldAlert, ShoppingBag, UserRound } from 'lucide-react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { StatCard } from '@/components/admin/StatCard';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

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

function initialForCase(value) {
    const normalized = String(value || '').replace(/[^a-zA-Z0-9]/g, '').trim();
    return normalized ? normalized.charAt(0).toUpperCase() : 'D';
}

function slaClass(state) {
    if (state === 'breach') return 'text-rose-600 dark:text-rose-400';
    if (state === 'warning') return 'text-amber-600 dark:text-amber-400';
    return 'text-slate-500 dark:text-slate-400';
}

export default function DisputesIndex({ header, rows, pagination, filters, index_url, status_options, summary, claim_url_template, unclaim_url_template }) {
    const f = filters || {};
    const status = f.status ?? '';
    const [query, setQuery] = useState(f.q ?? '');
    const [selected, setSelected] = useState(() => new Set());
    const allChecked = rows?.length > 0 && selected.size === rows.length;

    const actionUrl = (template, id) => String(template || '').replace('__ID__', String(id));

    const applyFilters = (next = {}) => {
        const params = { ...f, q: query, page: '1', ...next };
        if (!params.q) delete params.q;
        if (!params.status || params.status === 'all') delete params.status;
        router.get(index_url, params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleAll = (checked) => {
        setSelected(checked ? new Set((rows || []).map((row) => String(row.id ?? row.case))) : new Set());
    };

    const toggleOne = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            if (checked) next.add(id);
            else next.delete(id);
            return next;
        });
    };

    const claimDispute = (row) => {
        router.post(actionUrl(claim_url_template, row.id), {}, { preserveScroll: true });
    };

    const releaseDispute = (row) => {
        router.post(actionUrl(unclaim_url_template, row.id), {}, { preserveScroll: true });
    };

    const deleteDispute = (row) => {
        if (!row?.delete_href) return;
        const label = String(row.case ?? 'this dispute');
        if (!window.confirm(`Delete ${label}? This action cannot be undone.`)) return;

        router.delete(row.delete_href, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-4">
                    <StatCard label="Opened" value={String(summary?.opened ?? 0)} />
                    <StatCard label="Under review" value={String(summary?.under_review ?? 0)} />
                    <StatCard label="Resolved" value={String(summary?.resolved ?? 0)} />
                    <StatCard label="Escalated" value={String(summary?.escalated ?? 0)} />
                </div>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-700 lg:flex-row lg:items-center lg:justify-between">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters();
                            }}
                            className="w-full max-w-[448px]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                                <input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Search disputes by case or order..."
                                    className="h-[42px] w-full rounded-md border border-slate-200 bg-slate-50/70 pl-10 pr-3 text-[13px] font-medium text-slate-700 shadow-none outline-none transition placeholder:text-slate-400 focus:border-slate-300 focus:bg-white focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-slate-600 dark:focus:bg-slate-900"
                                />
                            </div>
                        </form>

                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-3">
                            <Button type="button" variant="outline" size="sm" className="h-[42px] rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800" onClick={() => applyFilters()}>
                                Apply
                            </Button>
                            <span className="hidden h-6 w-px bg-slate-200 dark:bg-slate-700 sm:block" />
                            <Select
                                value={status || 'all'}
                                onValueChange={(value) => applyFilters({ status: value })}
                            >
                                <SelectTrigger className="!h-[42px] !w-[160px] !min-w-[160px] shrink-0 rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    {(status_options || []).map((o) => (
                                        <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                                    <th className="min-w-[150px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Case</th>
                                    <th className="min-w-[220px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Order</th>
                                    <th className="min-w-[140px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Stage</th>
                                    <th className="min-w-[230px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Assignee</th>
                                    <th className="min-w-[190px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">SLA</th>
                                    <th className="min-w-[120px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Assignment</th>
                                    <th className="min-w-[124px] px-3 text-right text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows?.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                            No disputes found for the current filters.
                                        </td>
                                    </tr>
                                ) : (
                                    (rows || []).map((row, index) => {
                                        const id = String(row.id ?? row.case ?? index);
                                        const caseLabel = String(row.case ?? '—');
                                        return (
                                            <tr
                                                key={id}
                                                className="h-[65px] border-b border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-700/70 dark:hover:bg-slate-900/22"
                                            >
                                                <td className="px-4 align-middle">
                                                    <input type="checkbox" className="users-table-checkbox" checked={selected.has(id)} onChange={(event) => toggleOne(id, event.target.checked)} />
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <div className="flex items-center gap-3">
                                                        <div className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold', avatarTone(caseLabel))}>
                                                            {initialForCase(caseLabel)}
                                                        </div>
                                                        <Link href={String(row.href || '#')} className="block truncate text-[13px] font-semibold text-slate-950 transition-colors hover:text-primary dark:text-slate-100">
                                                            {caseLabel}
                                                        </Link>
                                                    </div>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <span className="inline-flex items-center gap-2 text-[13px] font-semibold text-slate-700 dark:text-slate-300">
                                                        <ShoppingBag className="h-4 w-4 text-slate-400" />
                                                        {String(row.order ?? '—')}
                                                    </span>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <StatusBadge status={String(row.stage ?? '')} className="px-2.5 py-1 text-[10px]" />
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <span className={cn('inline-flex items-center gap-2 text-[13px] font-medium', row.is_claimed ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500')}>
                                                        <UserRound className="h-4 w-4 text-slate-400" />
                                                        {String(row.assignee ?? 'Unassigned')}
                                                    </span>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <div className="flex items-center gap-2">
                                                        <span className={cn('inline-flex items-center gap-2 text-[13px] font-semibold', slaClass(row.sla_state))}>
                                                            <Clock className="h-4 w-4" />
                                                            {String(row.sla ?? '—')}
                                                        </span>
                                                        {row.is_escalated ? (
                                                            <span className="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-bold uppercase text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300">
                                                                <ShieldAlert className="h-3.5 w-3.5" />
                                                                Escalated
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    {row.is_assigned_to_me ? (
                                                        <Button size="sm" variant="outline" className="h-8 rounded-md px-3 text-[12px] font-semibold shadow-none" onClick={() => releaseDispute(row)}>Release</Button>
                                                    ) : (
                                                        <Button size="sm" className="h-8 rounded-md px-3 text-[12px] font-semibold shadow-none" onClick={() => claimDispute(row)} disabled={row.is_claimed}>Claim</Button>
                                                    )}
                                                </td>
                                                <td className="px-3 text-right align-middle">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button type="button" variant="outline" size="sm" className="h-8 rounded-md border-slate-200 bg-white px-3 text-[12px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                                                Actions
                                                                <ChevronDown className="ml-1.5 h-3.5 w-3.5" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="min-w-[180px] rounded-lg">
                                                            <DropdownMenuLabel className="px-2 py-1.5 text-[11px] uppercase tracking-normal text-slate-500">Dispute actions</DropdownMenuLabel>
                                                            <DropdownMenuItem asChild className="rounded-md text-[13px]">
                                                                <Link href={String(row.href || '#')}>View dispute details</Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild className="rounded-md text-[13px]">
                                                                <Link href={String(row.order_href || '#')}>View order</Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            {row.is_assigned_to_me ? (
                                                                <DropdownMenuItem className="rounded-md text-[13px]" onSelect={() => releaseDispute(row)}>
                                                                    Release dispute
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem className="rounded-md text-[13px]" disabled={row.is_claimed} onSelect={() => claimDispute(row)}>
                                                                    Claim dispute
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem className="rounded-md text-[13px] text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400" onSelect={() => deleteDispute(row)}>
                                                                Delete dispute
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
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

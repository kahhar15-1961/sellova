import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Ban, CheckCircle2, ChevronDown, ChevronLeft, ChevronRight, Filter, Search, Shield, Users } from 'lucide-react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

function fmtDate(iso) {
    if (!iso || iso === '—') return '—';
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
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/16 dark:text-emerald-300',
        'bg-sky-100 text-sky-700 dark:bg-sky-500/16 dark:text-sky-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/16 dark:text-violet-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/16 dark:text-rose-300',
        'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/16 dark:text-cyan-300',
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/16 dark:text-indigo-300',
    ];
    const index = Math.abs(
        String(seed || '')
            .split('')
            .reduce((sum, char) => sum + char.charCodeAt(0), 0),
    ) % tones.length;
    return tones[index];
}

function initialsForEmail(email) {
    const normalized = String(email || '').trim();
    if (!normalized) return 'U';
    return normalized.charAt(0).toUpperCase();
}

function metricCardTone(kind) {
    if (kind === 'active') return 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-500/12 dark:text-emerald-300 dark:border-emerald-500/20';
    if (kind === 'suspended') return 'bg-rose-50 text-rose-500 border-rose-100 dark:bg-rose-500/12 dark:text-rose-300 dark:border-rose-500/20';
    if (kind === 'staff') return 'bg-violet-50 text-violet-500 border-violet-100 dark:bg-violet-500/12 dark:text-violet-300 dark:border-violet-500/20';
    return 'bg-sky-50 text-sky-500 border-sky-100 dark:bg-sky-500/12 dark:text-sky-300 dark:border-sky-500/20';
}

function summaryItems(summary) {
    return [
        { key: 'total', label: 'Total users', value: String(summary?.total ?? 0), icon: Users, tone: 'total' },
        { key: 'active', label: 'Active', value: String(summary?.active ?? 0), icon: CheckCircle2, tone: 'active' },
        { key: 'suspended', label: 'Suspended', value: String(summary?.suspended ?? 0), icon: Ban, tone: 'suspended' },
        { key: 'staff', label: 'Staff', value: String(summary?.staff ?? 0), icon: Shield, tone: 'staff' },
    ];
}

function rolesList(value) {
    const raw = String(value || '').trim();
    if (!raw || raw === '—') return [];
    return raw
        .split(',')
        .map((role) => role.trim())
        .filter(Boolean);
}

function riskLabel(value) {
    const raw = String(value || 'low').trim();
    return raw || 'low';
}

function UsersPagination({ baseUrl, pagination, extraParams }) {
    const page = Number(pagination?.page ?? 1);
    const perPage = Number(pagination?.perPage ?? 10);
    const total = Number(pagination?.total ?? 0);
    const lastPage = Number(pagination?.lastPage ?? 1);
    const from = total === 0 ? 0 : (page - 1) * perPage + 1;
    const to = Math.min(page * perPage, total);

    const go = (nextPage) => {
        router.get(baseUrl, { ...extraParams, page: nextPage }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <div className="flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-[13px] text-slate-500 dark:text-slate-400">
                Showing {from} to {to} of {total} results
            </p>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => go(page - 1)}
                    className="h-9 rounded-lg border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    <ChevronLeft className="h-4 w-4" />
                    Previous
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={page >= lastPage}
                    onClick={() => go(page + 1)}
                    className="h-9 rounded-lg border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    Next
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}

/**
 * @param {{
 *   header: { title: string, description?: string, breadcrumbs?: { label: string, href?: string }[] },
 *   rows: Array<Record<string, unknown>>,
 *   pagination: Record<string, unknown>,
 *   filters: Record<string, string>,
 *   index_url: string,
 *   status_options: Array<{ value: string, label: string }>,
 *   summary: Record<string, number>,
 *   bulk_update_url: string,
 * }} props
 */
export default function UsersIndex({ header, rows, pagination, filters, index_url, status_options, summary, bulk_update_url }) {
    const f = filters || {};
    const [query, setQuery] = useState(f.q ?? '');
    const [status, setStatus] = useState(f.status ?? 'all');
    const [selected, setSelected] = useState(() => new Set());
    const [bulkStatus, setBulkStatus] = useState('active');
    const [bulkRisk, setBulkRisk] = useState('');
    const [selectAllFiltered, setSelectAllFiltered] = useState(false);

    const visibleIds = useMemo(() => rows.map((row) => Number(row.row_id)).filter(Boolean), [rows]);
    const allChecked = visibleIds.length > 0 && visibleIds.every((id) => selected.has(id));
    const selectedCount = selectAllFiltered ? Number(pagination?.total ?? selected.size) : selected.size;
    const paginationParams = {};
    const cards = summaryItems(summary);

    if (query.trim()) paginationParams.q = query.trim();
    if (status !== 'all') paginationParams.status = status;

    const applyFilters = (nextStatus = status, nextQuery = query) => {
        const params = {
            ...f,
            q: nextQuery.trim(),
            page: '1',
            status: nextStatus,
        };

        if (!params.q) delete params.q;
        if (!params.status || params.status === 'all') delete params.status;

        router.get(index_url, params, { preserveState: true, preserveScroll: true, replace: true });
    };

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
                filters: { q: query.trim(), status: status === 'all' ? '' : status },
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

    const updateUserState = (row, next = {}) => {
        const href = String(row.href || '');
        if (!href) return;

        router.post(
            `${href}/state`,
            {
                status: String(next.status ?? row.status ?? 'active'),
                risk_level: String(next.risk_level ?? row.risk ?? 'low'),
                reason: next.reason ?? 'row_action_update',
            },
            { preserveScroll: true },
        );
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            <div className="space-y-5">
                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    {cards.map(({ key, label, value, icon: Icon, tone }) => (
                        <div
                            key={key}
                            className="rounded-lg border border-slate-200 bg-white px-4 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800"
                        >
                            <div className="flex items-center gap-3">
                                <div className={cn('flex h-9 w-9 items-center justify-center rounded-md border', metricCardTone(tone))}>
                                    <Icon className="h-4 w-4" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">{label}</p>
                                    <p className="mt-1 text-[22px] font-bold leading-none text-slate-900 dark:text-white">{value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </section>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-700 lg:flex-row lg:items-center lg:justify-between">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters(status, query);
                            }}
                            className="w-full max-w-[448px]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                                <input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Search users by email, ID, or role..."
                                    className="h-9 w-full rounded-md border border-slate-200 bg-slate-50/70 pl-10 pr-3 text-[13px] font-medium text-slate-700 shadow-none outline-none transition placeholder:text-slate-400 focus:border-slate-300 focus:bg-white focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-slate-600 dark:focus:bg-slate-900"
                                />
                            </div>
                        </form>

                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-3">
                            <Select
                                value={status}
                                onValueChange={(value) => {
                                    setStatus(value);
                                    applyFilters(value, query);
                                }}
                            >
                                <SelectTrigger className="!h-[38px] !w-[158px] !min-w-[158px] shrink-0 rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    {(status_options || []).map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Button type="button" variant="outline" size="sm" className="h-[38px] shrink-0 gap-2 rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                <Filter className="h-4 w-4" />
                                Filters
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50/45 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/20">
                        <Button type="button" variant="outline" size="sm" className="h-[30px] rounded-md border-slate-200 bg-white px-3 text-[12px] font-semibold shadow-sm dark:border-slate-700 dark:bg-slate-800" onClick={() => toggleAll(!allChecked)}>
                            {allChecked ? 'Unselect page' : 'Select page'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-[30px] rounded-md border-slate-200 bg-white px-3 text-[12px] font-semibold shadow-sm dark:border-slate-700 dark:bg-slate-800"
                            onClick={() => setSelectAllFiltered((value) => !value)}
                        >
                            {selectAllFiltered ? 'All filtered selected' : 'Select all filtered'}
                        </Button>
                        <span className="mx-2 h-6 w-px bg-slate-200 dark:bg-slate-700" />
                        <Select value={bulkStatus} onValueChange={setBulkStatus}>
                            <SelectTrigger className="!h-[30px] !w-[132px] !min-w-[132px] shrink-0 rounded-md border-slate-200 bg-white px-3 text-[12px] font-semibold shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">active</SelectItem>
                                <SelectItem value="suspended">suspended</SelectItem>
                                <SelectItem value="closed">closed</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={bulkRisk || 'keep-risk'} onValueChange={(value) => setBulkRisk(value === 'keep-risk' ? '' : value)}>
                            <SelectTrigger className="!h-[30px] !w-[132px] !min-w-[132px] shrink-0 rounded-md border-slate-200 bg-white px-3 text-[12px] font-semibold shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="keep-risk">keep risk</SelectItem>
                                <SelectItem value="low">low</SelectItem>
                                <SelectItem value="medium">medium</SelectItem>
                                <SelectItem value="high">high</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            size="sm"
                            className="h-[30px] rounded-md bg-slate-200 px-4 text-[12px] font-semibold text-slate-500 shadow-none hover:bg-slate-200 disabled:opacity-100 dark:bg-slate-700 dark:text-slate-400"
                            disabled={selected.size === 0 && !selectAllFiltered}
                            onClick={applyBulk}
                        >
                            {selectAllFiltered ? 'Apply to all filtered' : `Apply to ${selectedCount} selected`}
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full border-collapse">
                            <thead>
                                <tr className="h-[38px] border-b border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/30">
                                    <th className="w-[38px] px-3 text-left">
                                        <input type="checkbox" className="users-table-checkbox" checked={allChecked} onChange={(event) => toggleAll(event.target.checked)} />
                                    </th>
                                    <th className="w-[52px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">ID</th>
                                    <th className="min-w-[245px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">User</th>
                                    <th className="min-w-[150px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Buyer profile</th>
                                    <th className="min-w-[155px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Seller profile</th>
                                    <th className="min-w-[94px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Status</th>
                                    <th className="min-w-[70px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Risk</th>
                                    <th className="min-w-[235px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Roles</th>
                                    <th className="min-w-[124px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Last login</th>
                                    <th className="min-w-[124px] px-3 text-right text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                            No users found for the current filters.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => {
                                        const id = Number(row.row_id);
                                        const email = String(row.email ?? '—');
                                        const roles = rolesList(row.roles);
                                        return (
                                            <tr
                                                key={id}
                                                className="h-[49px] border-b border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-700/70 dark:hover:bg-slate-900/22"
                                            >
                                                <td className="px-3 align-middle">
                                                    <input type="checkbox" className="users-table-checkbox" checked={selected.has(id)} onChange={(event) => toggleOne(id, event.target.checked)} />
                                                </td>
                                                <td className="px-3 align-middle text-[12px] font-medium text-slate-600 dark:text-slate-300">
                                                    <Link href={String(row.href || '#')} className="hover:text-primary">
                                                        {String(row.id ?? '—')}
                                                    </Link>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <div className="flex items-center gap-2.5">
                                                        <div className={cn('flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold', avatarTone(email))}>
                                                            {initialsForEmail(email)}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <Link
                                                                href={String(row.href || '#')}
                                                                className="block truncate text-[12px] font-semibold text-slate-950 transition-colors hover:text-primary dark:text-slate-100"
                                                            >
                                                                {email}
                                                            </Link>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    {row.buyer_href ? (
                                                        <Link href={String(row.buyer_href)} className="text-[12px] text-slate-700 transition-colors hover:text-primary dark:text-slate-300">
                                                            {String(row.buyer_profile ?? 'Open buyer')}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-[12px] text-slate-300 dark:text-slate-600">-</span>
                                                    )}
                                                </td>
                                                <td className="px-3 align-middle">
                                                    {row.seller_href ? (
                                                        <Link href={String(row.seller_href)} className="text-[12px] text-slate-700 transition-colors hover:text-primary dark:text-slate-300">
                                                            {String(row.seller_profile ?? 'Open seller')}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-[12px] text-slate-300 dark:text-slate-600">-</span>
                                                    )}
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <StatusBadge status={String(row.status ?? '')} className="px-2.5 py-0.5 text-[10px]" />
                                                </td>
                                                <td className="px-3 align-middle text-[12px] text-slate-700 dark:text-slate-300">
                                                    {riskLabel(row.risk)}
                                                </td>
                                                <td className="px-3 align-middle">
                                                    {roles.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {roles.map((role) => (
                                                                <Badge key={role} variant="outline" className="rounded px-2 py-0.5 text-[10px] font-medium normal-case tracking-normal">
                                                                    {role}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <span className="text-[12px] text-slate-300 dark:text-slate-600">-</span>
                                                    )}
                                                </td>
                                                <td className="px-3 align-middle text-[12px] text-slate-400 dark:text-slate-500">
                                                    {fmtDate(row.last_login)}
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
                                                            <DropdownMenuLabel className="px-2 py-1.5 text-[11px] uppercase tracking-normal text-slate-500">User actions</DropdownMenuLabel>
                                                            <DropdownMenuItem asChild className="rounded-md text-[13px]">
                                                                <Link href={String(row.href || '#')}>View / edit profile</Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuLabel className="px-2 py-1.5 text-[11px] uppercase tracking-normal text-slate-500">Change status</DropdownMenuLabel>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.status) === 'active'} onSelect={() => updateUserState(row, { status: 'active', reason: 'row_action_activate' })}>
                                                                Set active
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.status) === 'suspended'} onSelect={() => updateUserState(row, { status: 'suspended', reason: 'row_action_suspend' })}>
                                                                Suspend
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.status) === 'closed'} onSelect={() => updateUserState(row, { status: 'closed', reason: 'row_action_close' })}>
                                                                Close account
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuLabel className="px-2 py-1.5 text-[11px] uppercase tracking-normal text-slate-500">Risk level</DropdownMenuLabel>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.risk) === 'low'} onSelect={() => updateUserState(row, { risk_level: 'low', reason: 'row_action_risk_low' })}>
                                                                Mark low risk
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.risk) === 'medium'} onSelect={() => updateUserState(row, { risk_level: 'medium', reason: 'row_action_risk_medium' })}>
                                                                Mark medium risk
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.risk) === 'high'} onSelect={() => updateUserState(row, { risk_level: 'high', reason: 'row_action_risk_high' })}>
                                                                Mark high risk
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
                    <UsersPagination baseUrl={index_url} pagination={pagination} extraParams={paginationParams} />
                </section>
            </div>
        </AdminLayout>
    );
}

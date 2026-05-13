import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronDown, Filter, Package, Search, WalletCards } from 'lucide-react';
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

function initialForSeller(name, email) {
    const source = String(name || email || '').trim();
    return source ? source.charAt(0).toUpperCase() : 'S';
}

export default function SellerProfilesIndex({ header, rows, pagination, filters, index_url, verification_options, summary }) {
    const f = filters || {};
    const verification = f.verification ?? '';
    const [query, setQuery] = useState(f.q ?? '');
    const [selected, setSelected] = useState(() => new Set());
    const allChecked = rows?.length > 0 && selected.size === rows.length;

    const applyFilters = (next = {}) => {
        const params = { ...f, q: query, page: '1', ...next };
        if (!params.q) delete params.q;
        if (!params.verification || params.verification === 'all') delete params.verification;
        router.get(index_url, params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleAll = (checked) => {
        setSelected(checked ? new Set((rows || []).map((row) => String(row.seller ?? row.account))) : new Set());
    };

    const toggleOne = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            if (checked) next.add(id);
            else next.delete(id);
            return next;
        });
    };

    const updateStoreState = (row, storeStatus) => {
        const href = String(row.href || '');
        if (!href) return;

        router.post(
            `${href}/state`,
            {
                store_status: storeStatus,
                reason_code: storeStatus === 'suspended' ? 'manual_quality_hold' : 'policy_breach',
                reason: `row_action_store_${storeStatus}`,
            },
            { preserveScroll: true },
        );
    };

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
                                    placeholder="Search sellers by name or email..."
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
                                value={verification || 'all'}
                                onValueChange={(value) => applyFilters({ verification: value })}
                            >
                                <SelectTrigger className="!h-[42px] !w-[240px] !min-w-[240px] shrink-0 rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                    <SelectValue placeholder="All verification states" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All verification states</SelectItem>
                                    {(verification_options || []).map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
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
                                    <th className="w-[110px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Seller</th>
                                    <th className="min-w-[250px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">User</th>
                                    <th className="min-w-[210px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Display name</th>
                                    <th className="min-w-[130px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Verification</th>
                                    <th className="min-w-[120px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Store</th>
                                    <th className="min-w-[110px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Products</th>
                                    <th className="min-w-[150px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Pending withdrawals</th>
                                    <th className="min-w-[124px] px-4 text-right text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows?.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                            No sellers found for the current filters.
                                        </td>
                                    </tr>
                                ) : (
                                    (rows || []).map((row, index) => {
                                        const id = String(row.seller ?? row.account ?? index);
                                        const account = String(row.account ?? '—');
                                        const displayName = String(row.display_name ?? '—');
                                        return (
                                            <tr
                                                key={id}
                                                className="h-[65px] border-b border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-700/70 dark:hover:bg-slate-900/22"
                                            >
                                                <td className="px-4 align-middle">
                                                    <input type="checkbox" className="users-table-checkbox" checked={selected.has(id)} onChange={(event) => toggleOne(id, event.target.checked)} />
                                                </td>
                                                <td className="px-3 align-middle text-[12px] font-bold text-slate-600 dark:text-slate-300">
                                                    <Link href={String(row.href || '#')} className="hover:text-primary">
                                                        {String(row.seller ?? '—')}
                                                    </Link>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <div className="flex items-center gap-3">
                                                        <div className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold', avatarTone(displayName || account))}>
                                                            {initialForSeller(displayName, account)}
                                                        </div>
                                                        {row.user_href ? (
                                                            <Link href={String(row.user_href)} className="block truncate text-[13px] font-semibold text-slate-950 transition-colors hover:text-primary dark:text-slate-100">
                                                                {account}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-[13px] font-semibold text-slate-950 dark:text-slate-100">{account}</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-3 align-middle text-[13px] font-semibold text-slate-700 dark:text-slate-300">
                                                    {displayName}
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <StatusBadge status={String(row.verification ?? '')} className="px-2.5 py-1 text-[10px]" />
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <StatusBadge status={String(row.store ?? '')} className="px-2.5 py-1 text-[10px]" />
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <span className="inline-flex items-center gap-2 text-[13px] font-semibold text-slate-700 dark:text-slate-300">
                                                        <Package className="h-4 w-4 text-slate-400" />
                                                        {String(row.products ?? '0')}
                                                    </span>
                                                </td>
                                                <td className="px-3 align-middle">
                                                    <span className="inline-flex items-center gap-2 text-[13px] font-semibold text-slate-700 dark:text-slate-300">
                                                        <WalletCards className="h-4 w-4 text-amber-500" />
                                                        {String(row.pending_withdrawals ?? '0')}
                                                    </span>
                                                </td>
                                                <td className="px-4 text-right align-middle">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button type="button" variant="outline" size="sm" className="h-8 rounded-md border-slate-200 bg-white px-3 text-[12px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                                                Actions
                                                                <ChevronDown className="ml-1.5 h-3.5 w-3.5" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="min-w-[190px] rounded-lg">
                                                            <DropdownMenuLabel className="px-2 py-1.5 text-[11px] uppercase tracking-normal text-slate-500">Seller actions</DropdownMenuLabel>
                                                            <DropdownMenuItem asChild className="rounded-md text-[13px]">
                                                                <Link href={String(row.href || '#')}>View seller profile</Link>
                                                            </DropdownMenuItem>
                                                            {row.user_href ? (
                                                                <DropdownMenuItem asChild className="rounded-md text-[13px]">
                                                                    <Link href={String(row.user_href)}>View / edit user</Link>
                                                                </DropdownMenuItem>
                                                            ) : null}
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuLabel className="px-2 py-1.5 text-[11px] uppercase tracking-normal text-slate-500">Store status</DropdownMenuLabel>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.store) === 'active'} onSelect={() => updateStoreState(row, 'active')}>
                                                                Set store active
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem className="rounded-md text-[13px]" disabled={String(row.store) === 'suspended'} onSelect={() => updateStoreState(row, 'suspended')}>
                                                                Suspend store
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

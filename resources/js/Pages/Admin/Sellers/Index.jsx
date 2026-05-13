import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Archive, CheckCircle2, Download, Filter, Inbox, Search, ShieldAlert, UserCircle, XCircle } from 'lucide-react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const tabs = [
    ['pending', 'Pending'],
    ['mine', 'Mine'],
    ['escalated', 'Escalated'],
    ['all', 'All'],
    ['approved', 'Approved'],
    ['rejected', 'Rejected'],
    ['expired', 'Expired'],
];

function relativeTime(iso) {
    if (!iso) return '—';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '—';
    const seconds = Math.max(1, Math.floor((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} min${minutes === 1 ? '' : 's'} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? '' : 's'} ago`;
}

function caseCode(id) {
    return `VC-${String(2000 + Number(id || 0)).padStart(4, '0')}`;
}

function initial(email) {
    return String(email || 'S').trim().charAt(0).toUpperCase() || 'S';
}

function avatarTone(seed) {
    const tones = [
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/16 dark:text-emerald-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/16 dark:text-violet-300',
        'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/16 dark:text-cyan-300',
        'bg-amber-100 text-amber-700 dark:bg-amber-500/16 dark:text-amber-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/16 dark:text-rose-300',
    ];
    const index = Math.abs(String(seed || '').split('').reduce((sum, char) => sum + char.charCodeAt(0), 0)) % tones.length;
    return tones[index];
}

function statTone(kind) {
    if (kind === 'pending') return 'border-amber-100 bg-amber-50 text-amber-500 dark:border-amber-500/20 dark:bg-amber-500/12 dark:text-amber-300';
    if (kind === 'mine') return 'border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-500/20 dark:bg-blue-500/12 dark:text-blue-300';
    if (kind === 'escalated') return 'border-rose-100 bg-rose-50 text-rose-500 dark:border-rose-500/20 dark:bg-rose-500/12 dark:text-rose-300';
    if (kind === 'approved') return 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-500/20 dark:bg-emerald-500/12 dark:text-emerald-300';
    return 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function riskTone(risk) {
    const value = String(risk || 'low').toLowerCase();
    if (value === 'high') return 'text-rose-600 dark:text-rose-300';
    if (value === 'medium') return 'text-orange-600 dark:text-orange-300';
    return 'text-emerald-600 dark:text-emerald-300';
}

function statusTone(status) {
    const value = String(status || '').toLowerCase();
    if (['approved', 'verified'].includes(value)) return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/12 dark:text-emerald-300';
    if (value === 'rejected') return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    if (value === 'expired') return 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-400';
    if (value === 'under_review') return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/12 dark:text-blue-300';
    if (value === 'escalated') return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/12 dark:text-rose-300';
    return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/12 dark:text-amber-300';
}

function statusLabel(status, row) {
    if (row?.sla_state === 'breach' && ['submitted', 'under_review'].includes(String(status))) return 'Escalated';
    if (String(status) === 'submitted') return 'Pending';
    return String(status || 'pending').replaceAll('_', ' ');
}

function queueStats(summary) {
    return [
        { key: 'pending', label: 'Pending', value: summary?.pending ?? 0, icon: Archive },
        { key: 'mine', label: 'My queue', value: summary?.mine ?? 0, icon: UserCircle },
        { key: 'escalated', label: 'Escalated', value: summary?.escalated ?? 0, icon: ShieldAlert },
        { key: 'approved', label: 'Approved', value: summary?.approved ?? 0, icon: CheckCircle2 },
        { key: 'rejected', label: 'Rejected', value: summary?.rejected ?? 0, icon: XCircle },
    ];
}

export default function SellersIndex({ header, tab, q, rows = [], pagination, summary, export_url: exportUrl }) {
    const flash = usePage().props.flash ?? {};
    const qRef = useRef(null);
    const activeTab = tab || 'pending';
    const from = pagination?.from ?? 0;
    const to = pagination?.to ?? 0;
    const total = pagination?.total ?? 0;

    const applyFilters = (next = {}) => {
        router.get(
            '/admin/sellers',
            {
                tab: next.tab ?? activeTab,
                q: next.q !== undefined ? next.q : q,
                page: next.page ?? 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            {flash.success ? <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/12 dark:text-emerald-200">{flash.success}</div> : null}
            {flash.error ? <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-500/20 dark:bg-red-500/12 dark:text-red-200">{flash.error}</div> : null}

            <div className="space-y-6">
                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    {queueStats(summary).map(({ key, label, value, icon: Icon }) => (
                        <div key={key} className="rounded-lg border border-slate-200 bg-white px-5 py-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{label}</p>
                                    <p className="mt-2 text-[25px] font-extrabold leading-none text-slate-950 dark:text-white">{value}</p>
                                </div>
                                <div className={cn('flex h-9 w-9 items-center justify-center rounded-lg border', statTone(key))}>
                                    <Icon className="h-4 w-4" />
                                </div>
                            </div>
                        </div>
                    ))}
                </section>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex h-[55px] items-center gap-6 overflow-x-auto border-b border-slate-200 px-7 dark:border-slate-700">
                        {tabs.map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => applyFilters({ tab: value, page: 1 })}
                                className={cn(
                                    'relative h-full shrink-0 px-0 text-[13px] font-bold text-slate-500 transition hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100',
                                    activeTab === value && 'text-[#4f46ff] dark:text-[#8470ff]',
                                )}
                            >
                                {label}
                                {activeTab === value ? <span className="absolute bottom-0 left-0 h-0.5 w-full rounded-full bg-[#4f46ff] dark:bg-[#8470ff]" /> : null}
                            </button>
                        ))}
                    </div>

                    <div className="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 dark:border-slate-700 lg:flex-row lg:items-center lg:justify-between">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters({ q: qRef.current?.value ?? '', page: 1 });
                            }}
                            className="flex w-full flex-col gap-2 sm:flex-row lg:max-w-[512px]"
                        >
                            <div className="relative flex-1">
                                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                                <input
                                    ref={qRef}
                                    defaultValue={q}
                                    placeholder="Search by account email..."
                                    className="h-[38px] w-full rounded-md border border-slate-200 bg-slate-50/70 pl-10 pr-3 text-[13px] font-medium text-slate-700 shadow-none outline-none transition placeholder:text-slate-400 focus:border-slate-300 focus:bg-white focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-slate-600 dark:focus:bg-slate-900"
                                />
                            </div>
                            <Button type="submit" className="h-[38px] rounded-lg bg-slate-950 px-5 text-[13px] font-bold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                                Search
                            </Button>
                        </form>

                        <div className="flex shrink-0 flex-wrap items-center gap-3">
                            <span className="inline-flex h-8 items-center rounded-md border border-slate-200 bg-slate-50 px-3 text-[12px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                                Showing {from || 0}-{to || 0} of {total}
                            </span>
                            {exportUrl ? (
                                <a href={exportUrl} className="inline-flex h-[38px] items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-[13px] font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/50">
                                    <Download className="h-4 w-4 text-slate-400" />
                                    Export CSV
                                </a>
                            ) : null}
                        </div>
                    </div>

                    {!rows.length ? (
                        <div className="m-5 flex min-h-[344px] flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white text-center dark:border-slate-700 dark:bg-slate-800">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                                <Filter className="h-7 w-7 text-slate-400" />
                            </div>
                            <h3 className="mt-5 text-base font-extrabold text-slate-950 dark:text-white">No cases match this view</h3>
                            <p className="mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">
                                There are currently no verification cases under the <span className="font-bold text-slate-700 dark:text-slate-200">"{tabs.find(([value]) => value === activeTab)?.[1] ?? 'Selected'}"</span> lifecycle tab that match your search criteria.
                            </p>
                        </div>
                    ) : (
                        <div className="admin-scrollbar overflow-x-auto">
                            <table className="w-full min-w-[920px] text-left">
                                <thead>
                                    <tr className="h-[42px] border-b border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/30">
                                        {['Case ID', 'Account', 'Verification Type', 'Risk Level', 'Submitted', 'Status'].map((column) => (
                                            <th key={column} className="px-6 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{column}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => {
                                        const risk = String(row.risk_level || 'low').toLowerCase();
                                        const status = statusLabel(row.status, row);
                                        const sellerName = String(row.seller_display_name ?? row.display_name ?? 'Seller');
                                        const accountEmail = String(row.account_email ?? 'No email');
                                        return (
                                            <tr key={row.id} className="h-[71px] border-b border-slate-100 transition hover:bg-slate-50/70 dark:border-slate-700/70 dark:hover:bg-slate-900/25">
                                                <td className="px-6 text-[13px] font-extrabold text-[#4f46ff] dark:text-[#8470ff]">
                                                    <Link href={row.workspace_url}>{caseCode(row.id)}</Link>
                                                </td>
                                                <td className="px-6">
                                                    <div className="flex items-center gap-3">
                                                        <span className={cn('flex h-8 w-8 items-center justify-center rounded-full text-[12px] font-bold', avatarTone(accountEmail || sellerName))}>
                                                            {initial(sellerName || accountEmail)}
                                                        </span>
                                                        <span className="min-w-0">
                                                            <span className="block max-w-[220px] truncate text-[13px] font-bold text-slate-900 dark:text-slate-100">{sellerName}</span>
                                                            <span className="block max-w-[220px] truncate text-[12px] font-medium text-slate-500 dark:text-slate-400">{accountEmail}</span>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6">
                                                    <p className="text-[13px] font-bold text-slate-700 dark:text-slate-200">{row.verification_type ?? 'Identity Verification'}</p>
                                                    <p className="mt-0.5 text-[12px] font-medium text-slate-500 dark:text-slate-400">{row.document_label ?? 'Passport'}</p>
                                                </td>
                                                <td className="px-6">
                                                    <span className={cn('inline-flex items-center gap-1.5 text-[13px] font-bold capitalize', riskTone(risk))}>
                                                        <ShieldAlert className="h-3.5 w-3.5" />
                                                        {risk}
                                                    </span>
                                                </td>
                                                <td className="px-6">
                                                    <span className="inline-flex items-center gap-2 text-[13px] font-semibold text-slate-500 dark:text-slate-400">
                                                        <Inbox className="h-3.5 w-3.5" />
                                                        {relativeTime(row.submitted_at)}
                                                    </span>
                                                </td>
                                                <td className="px-6">
                                                    <span className={cn('inline-flex rounded-md border px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.04em]', statusTone(status))}>
                                                        {status}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-[13px] font-semibold text-slate-500 dark:text-slate-400">
                            Showing {from || 0}-{to || 0} of {total}
                        </p>
                        {pagination?.last_page > 1 ? (
                            <div className="flex items-center gap-2">
                                <Button type="button" variant="outline" size="sm" disabled={pagination.current_page <= 1} onClick={() => applyFilters({ page: pagination.current_page - 1 })} className="h-9 rounded-lg border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    Previous
                                </Button>
                                <Button type="button" variant="outline" size="sm" disabled={pagination.current_page >= pagination.last_page} onClick={() => applyFilters({ page: pagination.current_page + 1 })} className="h-9 rounded-lg border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    Next
                                </Button>
                            </div>
                        ) : null}
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}

import { Form, Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { CalendarClock, ChevronDown, Ellipsis, Filter, Megaphone, PackageSearch, PauseCircle, PlayCircle, Search, Target, Trash2 } from 'lucide-react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { DateTimeField, EnterpriseMultiSelect, EnterpriseSelect, Field, SectionHeader } from '@/components/admin/EnterpriseForm';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/utils';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

function inputDateTime(iso) {
    if (!iso) return '';
    try {
        const date = new Date(iso);
        const offset = date.getTimezoneOffset();
        const local = new Date(date.getTime() - offset * 60000);
        return local.toISOString().slice(0, 16);
    } catch {
        return '';
    }
}

function percent(value) {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? `${(number * 100).toFixed(2)}%` : '0.00%';
}

function money(value) {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? number.toFixed(2) : '0.00';
}

function statusFor(row) {
    const now = Date.now();
    if (!row.is_active) return 'paused';
    if (row.starts_at && new Date(row.starts_at).getTime() > now) return 'scheduled';
    if (row.ends_at && new Date(row.ends_at).getTime() < now) return 'expired';
    return 'active';
}

function discountLabel(row) {
    if (row.discount_type === 'percentage') return percent(row.discount_value);
    if (row.discount_type === 'shipping') return 'Free shipping';
    return formatMoney(row.discount_value, row.currency, { currencyDisplay: 'code' });
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

function initialForCampaign(value) {
    const normalized = String(value || '').replace(/[^a-zA-Z0-9]/g, '').trim();
    return normalized ? normalized.charAt(0).toUpperCase() : 'P';
}

function CampaignScheduleModal({ row, updateUrl }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    Edit schedule
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Update campaign controls</DialogTitle>
                    <DialogDescription>{row.title} · {row.code}</DialogDescription>
                </DialogHeader>
                <Form action={updateUrl} method="patch" className="grid gap-4 sm:grid-cols-2">
                    <Field label="Starts">
                        <DateTimeField name="starts_at" defaultValue={inputDateTime(row.starts_at)} />
                    </Field>
                    <Field label="Ends">
                        <DateTimeField name="ends_at" defaultValue={inputDateTime(row.ends_at)} />
                    </Field>
                    <Field label="Daily start">
                        <DateTimeField name="daily_start_time" type="time" defaultValue={row.daily_start_time || ''} />
                    </Field>
                    <Field label="Daily end">
                        <DateTimeField name="daily_end_time" type="time" defaultValue={row.daily_end_time || ''} />
                    </Field>
                    <Field label="Priority">
                        <Input name="priority" type="number" min="0" defaultValue={row.priority ?? 100} />
                    </Field>
                    <Field label="Usage limit">
                        <Input name="usage_limit" type="number" min="0" defaultValue={row.usage_limit || ''} placeholder="Unlimited" />
                    </Field>
                    <div className="flex justify-end gap-2 sm:col-span-2">
                        <Button type="submit">Save controls</Button>
                    </div>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function PromotionsIndex({
    header,
    rows = [],
    store_url,
    update_url_template,
    toggle_url_template,
    delete_url_template,
    seller_options = [],
    category_options = [],
    product_options = [],
    type_options = [],
}) {
    const [query, setQuery] = useState('');
    const [stateFilter, setStateFilter] = useState('all');
    const [selected, setSelected] = useState(() => new Set());
    const activeCampaigns = rows.filter((row) => statusFor(row) === 'active').length;
    const scheduledCampaigns = rows.filter((row) => statusFor(row) === 'scheduled').length;
    const catalogCampaigns = rows.filter((row) => row.campaign_type === 'catalog').length;
    const filteredRows = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return rows.filter((row) => {
            const state = statusFor(row);
            const matchesState = stateFilter === 'all' || state === stateFilter;
            const haystack = [
                row.title,
                row.code,
                row.badge,
                row.marketing_channel,
                row.description,
                row.campaign_type,
                row.scope_type,
            ].join(' ').toLowerCase();
            return matchesState && (!needle || haystack.includes(needle));
        });
    }, [query, rows, stateFilter]);
    const allChecked = filteredRows.length > 0 && selected.size === filteredRows.length;

    const toggleAll = (checked) => {
        setSelected(checked ? new Set(filteredRows.map((row) => String(row.id))) : new Set());
    };

    const toggleOne = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            if (checked) next.add(id);
            else next.delete(id);
            return next;
        });
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            <div className="space-y-6">
                <div className="grid gap-3 md:grid-cols-4">
                    <Card>
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">Total campaigns</p>
                                <p className="mt-1 text-2xl font-bold">{rows.length}</p>
                            </div>
                            <Megaphone className="h-5 w-5 text-primary" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">Live now</p>
                                <p className="mt-1 text-2xl font-bold">{activeCampaigns}</p>
                            </div>
                            <PlayCircle className="h-5 w-5 text-emerald-600" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">Scheduled</p>
                                <p className="mt-1 text-2xl font-bold">{scheduledCampaigns}</p>
                            </div>
                            <CalendarClock className="h-5 w-5 text-amber-600" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">Catalog pricing</p>
                                <p className="mt-1 text-2xl font-bold">{catalogCampaigns}</p>
                            </div>
                            <PackageSearch className="h-5 w-5 text-indigo-600" />
                        </CardContent>
                    </Card>
                </div>

                <Dialog>
                    <div className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white px-4 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-[15px] font-bold text-slate-950 dark:text-slate-100">Campaign operations</h2>
                            <p className="mt-1 text-[13px] text-slate-500 dark:text-slate-400">Launch catalog campaigns or promo codes from a controlled modal workflow.</p>
                        </div>
                        <DialogTrigger asChild>
                            <Button className="h-[42px] gap-2 rounded-md bg-slate-950 px-4 text-[13px] font-semibold text-white shadow-none hover:bg-slate-900 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white">
                                <Megaphone className="h-4 w-4" />
                                New campaign
                            </Button>
                        </DialogTrigger>
                    </div>
                    <DialogContent className="max-h-[92vh] max-w-6xl overflow-y-auto p-0">
                        <DialogHeader className="border-b bg-muted/20 px-6 py-5 text-left">
                            <DialogTitle>Create enterprise campaign</DialogTitle>
                            <DialogDescription>
                                Build scheduled promo codes or automatic catalog discounts with precise product, seller, category, or fulfillment targeting.
                            </DialogDescription>
                        </DialogHeader>
                    <CardHeader className="hidden border-b bg-muted/20">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <CardTitle>Create enterprise campaign</CardTitle>
                                <CardDescription>
                                    Build scheduled promo codes or automatic catalog discounts with precise product, seller, category, or fulfillment targeting.
                                </CardDescription>
                            </div>
                            <Badge variant="outline" className="w-fit gap-2">
                                <Target className="h-3.5 w-3.5" />
                                Rule based
                            </Badge>
                        </div>
                    </CardHeader>
                    <div className="px-6 pb-6">
                        <Form action={store_url} method="post" className="space-y-6 pt-6">
                            <section className="grid gap-4 rounded-md border bg-background p-4 xl:grid-cols-12">
                                <SectionHeader icon={Megaphone} title="Campaign identity" description="Define the commercial objective, promo code, channel, and internal brief." className="xl:col-span-12" />
                                <Field label="Campaign type" required className="xl:col-span-2">
                                    <EnterpriseSelect
                                        name="campaign_type"
                                        defaultValue="catalog"
                                        placeholder={null}
                                        options={[
                                            { value: 'catalog', label: 'Catalog campaign' },
                                            { value: 'coupon', label: 'Promo code' },
                                        ]}
                                    />
                                </Field>
                                <Field label="Code" required className="xl:col-span-2">
                                <Input name="code" placeholder="BIRTHDAY10" required />
                                </Field>
                                <Field label="Campaign name" required className="xl:col-span-4">
                                <Input name="title" placeholder="Birthday celebration sale" required />
                                </Field>
                                <Field label="Badge" className="xl:col-span-2">
                                <Input name="badge" placeholder="10% OFF" />
                                </Field>
                                <Field label="Channel" className="xl:col-span-2">
                                <Input name="marketing_channel" placeholder="Homepage, app, email" />
                                </Field>
                                <Field label="Internal campaign brief" className="xl:col-span-12">
                                    <Textarea name="description" rows={4} placeholder="Commercial objective, eligibility notes, margin guardrails, and customer-facing messaging" />
                                </Field>
                            </section>

                            <section className="grid gap-4 rounded-md border bg-background p-4 xl:grid-cols-12">
                                <SectionHeader icon={PackageSearch} title="Commercial rules" description="Set discount mechanics, caps, minimum spend, usage limits, and campaign priority." className="xl:col-span-12" />
                                <Field label="Discount type" required className="xl:col-span-2">
                                    <EnterpriseSelect
                                        name="discount_type"
                                        defaultValue="percentage"
                                        placeholder={null}
                                        options={[
                                            { value: 'percentage', label: 'Percentage' },
                                            { value: 'fixed', label: 'Fixed amount' },
                                            { value: 'shipping', label: 'Shipping waiver' },
                                        ]}
                                    />
                                </Field>
                                <Field label="Discount value" required className="xl:col-span-2">
                                <Input name="discount_value" type="number" min="0" step="0.01" defaultValue="10" required />
                                </Field>
                                <Field label="Currency" required className="xl:col-span-2">
                                <Input name="currency" defaultValue="BDT" maxLength={3} required />
                                </Field>
                                <Field label="Min spend" className="xl:col-span-2">
                                <Input name="min_spend" type="number" min="0" step="0.01" defaultValue="0" />
                                </Field>
                                <Field label="Max discount" hint="Optional" className="xl:col-span-2">
                                <Input name="max_discount_amount" type="number" min="0" step="0.01" placeholder="Optional cap" />
                                </Field>
                                <Field label="Priority" className="xl:col-span-2">
                                <Input name="priority" type="number" min="0" defaultValue="100" />
                                </Field>
                            </section>

                            <section className="grid gap-4 rounded-md border bg-background p-4 xl:grid-cols-12">
                                <SectionHeader icon={CalendarClock} title="Schedule and audience" description="Control exactly when the campaign runs and which catalog records are eligible." className="xl:col-span-12" />
                                <Field label="Starts" className="xl:col-span-3">
                                    <DateTimeField name="starts_at" />
                                </Field>
                                <Field label="Ends" className="xl:col-span-3">
                                    <DateTimeField name="ends_at" />
                                </Field>
                                <Field label="Daily start time" hint="Optional window" className="xl:col-span-3">
                                    <DateTimeField name="daily_start_time" type="time" />
                                </Field>
                                <Field label="Daily end time" hint="Optional window" className="xl:col-span-3">
                                    <DateTimeField name="daily_end_time" type="time" />
                                </Field>
                                <Field label="Usage limit" className="xl:col-span-3">
                                <Input name="usage_limit" type="number" min="0" placeholder="Unlimited" />
                                </Field>
                                <Field label="Audience scope" required className="xl:col-span-3">
                                    <EnterpriseSelect
                                        name="scope_type"
                                        defaultValue="all"
                                        placeholder={null}
                                        options={[
                                            { value: 'all', label: 'All eligible products' },
                                            { value: 'products', label: 'Selected products' },
                                            { value: 'sellers', label: 'Selected sellers' },
                                            { value: 'categories', label: 'Selected categories' },
                                            { value: 'product_types', label: 'Fulfillment types' },
                                        ]}
                                    />
                                </Field>

                                <Field label="Target products" className="xl:col-span-3">
                                    <EnterpriseMultiSelect name="target_product_ids" options={product_options} />
                                </Field>
                                <Field label="Target sellers" className="xl:col-span-3">
                                    <EnterpriseMultiSelect name="target_seller_profile_ids" options={seller_options} />
                                </Field>
                                <Field label="Target categories" className="xl:col-span-3">
                                    <EnterpriseMultiSelect name="target_category_ids" options={category_options} />
                                </Field>
                                <Field label="Target fulfillment" className="xl:col-span-3">
                                    <EnterpriseMultiSelect name="target_product_types" options={type_options} />
                                </Field>
                            </section>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <input type="hidden" name="is_active" value="0" />
                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <input type="checkbox" name="is_active" value="1" defaultChecked />
                                    Active once schedule is valid
                                </label>
                                <Button type="submit" className="gap-2">
                                    <Megaphone className="h-4 w-4" />
                                    Create campaign
                                </Button>
                            </div>
                        </Form>
                    </div>
                    </DialogContent>
                </Dialog>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-700 lg:flex-row lg:items-center lg:justify-between">
                        <form
                            onSubmit={(event) => event.preventDefault()}
                            className="w-full max-w-[448px]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                                <input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Search campaigns by code, title, or channel..."
                                    className="h-[42px] w-full rounded-md border border-slate-200 bg-slate-50/70 pl-10 pr-3 text-[13px] font-medium text-slate-700 shadow-none outline-none transition placeholder:text-slate-400 focus:border-slate-300 focus:bg-white focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-slate-600 dark:focus:bg-slate-900"
                                />
                            </div>
                        </form>

                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-3">
                            <Button type="button" variant="outline" size="sm" className="h-[42px] rounded-md border-slate-200 bg-white px-4 text-[13px] font-semibold shadow-none dark:border-slate-700 dark:bg-slate-800">
                                Apply
                            </Button>
                            <span className="hidden h-6 w-px bg-slate-200 dark:bg-slate-700 sm:block" />
                            <select
                                value={stateFilter}
                                onChange={(event) => setStateFilter(event.target.value)}
                                className="h-[42px] w-[160px] min-w-[160px] shrink-0 rounded-md border border-slate-200 bg-white px-4 text-[13px] font-semibold text-slate-700 shadow-none outline-none transition focus:border-slate-300 focus:ring-2 focus:ring-slate-200/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="expired">Expired</option>
                                <option value="paused">Paused</option>
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
                                    <th className="min-w-[310px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Campaign</th>
                                    <th className="min-w-[130px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Type</th>
                                    <th className="min-w-[170px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Discount</th>
                                    <th className="min-w-[220px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Audience</th>
                                    <th className="min-w-[230px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Schedule</th>
                                    <th className="min-w-[120px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Usage</th>
                                    <th className="min-w-[120px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Status</th>
                                    <th className="min-w-[300px] px-3 text-left text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">Actions</th>
                                    <th className="w-12 px-4" />
                                </tr>
                            </thead>
                            <tbody>
                                {filteredRows.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                            No campaigns found for the current filters.
                                        </td>
                                    </tr>
                                ) : (
                                    filteredRows.map((row) => {
                                        const active = Boolean(row.is_active);
                                        const state = statusFor(row);
                                        const id = String(row.id);
                                        return (
                                            <tr
                                                key={row.id}
                                                className="min-h-[65px] border-b border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-700/70 dark:hover:bg-slate-900/22"
                                            >
                                                <td className="px-4 py-4 align-middle">
                                                    <input type="checkbox" className="users-table-checkbox" checked={selected.has(id)} onChange={(event) => toggleOne(id, event.target.checked)} />
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <div className="flex items-start gap-3">
                                                        <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold ${avatarTone(row.code || row.title)}`}>
                                                            {initialForCampaign(row.code || row.title)}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="truncate text-[13px] font-semibold text-slate-950 dark:text-slate-100">{row.title}</div>
                                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                                <Badge variant="outline" className="rounded px-2 py-0.5 text-[10px] font-semibold">{row.code}</Badge>
                                                                {row.badge ? <Badge className="rounded px-2 py-0.5 text-[10px] font-semibold">{row.badge}</Badge> : null}
                                                                {row.marketing_channel ? <Badge variant="secondary" className="rounded px-2 py-0.5 text-[10px] font-semibold">{row.marketing_channel}</Badge> : null}
                                                            </div>
                                                            <p className="mt-2 line-clamp-2 max-w-md text-[12px] text-slate-500 dark:text-slate-400">{row.description || 'No brief added.'}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <div className="text-[13px] font-semibold capitalize text-slate-700 dark:text-slate-300">{row.campaign_type || 'coupon'}</div>
                                                    <div className="mt-1 text-[12px] text-slate-400 dark:text-slate-500">Priority {row.priority ?? 100}</div>
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <div className="text-[13px] font-semibold text-slate-700 dark:text-slate-300">{discountLabel(row)}</div>
                                                    {row.max_discount_amount ? <div className="mt-1 text-[12px] text-slate-500 dark:text-slate-400">Cap {formatMoney(row.max_discount_amount, row.currency, { currencyDisplay: 'code' })}</div> : null}
                                                    <div className="mt-1 text-[12px] text-slate-500 dark:text-slate-400">Min {formatMoney(row.min_spend, row.currency, { currencyDisplay: 'code' })}</div>
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <div className="inline-flex items-center gap-2 text-[13px] font-semibold capitalize text-slate-700 dark:text-slate-300">
                                                        <Target className="h-4 w-4 text-slate-400" />
                                                        {String(row.scope_type || 'all').replace('_', ' ')}
                                                    </div>
                                                    <div className="mt-1 text-[12px] text-slate-500 dark:text-slate-400">
                                                        Products {(row.target_product_ids || []).length} · Sellers {(row.target_seller_profile_ids || []).length} · Categories {(row.target_category_ids || []).length}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-4 align-middle text-[13px] font-medium text-slate-500 dark:text-slate-400">
                                                    <div>{fmtDate(row.starts_at)}</div>
                                                    <div className="mt-1">{fmtDate(row.ends_at)}</div>
                                                    {row.daily_start_time || row.daily_end_time ? (
                                                        <div className="mt-1 text-[12px] font-semibold text-slate-700 dark:text-slate-300">
                                                            Daily {row.daily_start_time || '00:00'} - {row.daily_end_time || '23:59'}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-4 align-middle text-[13px] font-semibold text-slate-700 dark:text-slate-300">
                                                    {row.used_count ?? 0}
                                                    {row.usage_limit ? ` / ${row.usage_limit}` : ' / Unlimited'}
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <StatusBadge status={state} className="px-2.5 py-1 text-[10px]" />
                                                </td>
                                                <td className="px-3 py-4 align-middle">
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 rounded-md px-3 text-[12px] font-semibold shadow-none"
                                                            onClick={() => router.post(toggle_url_template.replace('__ID__', row.id), { is_active: !active }, { preserveScroll: true })}
                                                        >
                                                            {active ? <PauseCircle className="h-4 w-4" /> : <PlayCircle className="h-4 w-4" />}
                                                            {active ? 'Pause' : 'Activate'}
                                                        </Button>
                                                        <CampaignScheduleModal row={row} updateUrl={update_url_template.replace('__ID__', row.id)} />
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            className="h-8 rounded-md px-3 text-[12px] font-semibold shadow-none"
                                                            onClick={() => {
                                                                if (window.confirm(`Delete campaign ${row.code}?`)) {
                                                                    router.delete(delete_url_template.replace('__ID__', row.id), { preserveScroll: true });
                                                                }
                                                            }}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                            Delete
                                                        </Button>
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
                </section>
            </div>
        </AdminLayout>
    );
}

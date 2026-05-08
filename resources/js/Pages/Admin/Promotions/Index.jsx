import { Form, Head, router } from '@inertiajs/react';
import { CalendarClock, Megaphone, PackageSearch, PauseCircle, PlayCircle, Target, Trash2 } from 'lucide-react';
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
    return `${row.currency || ''} ${money(row.discount_value)}`.trim();
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
    const activeCampaigns = rows.filter((row) => statusFor(row) === 'active').length;
    const scheduledCampaigns = rows.filter((row) => statusFor(row) === 'scheduled').length;
    const catalogCampaigns = rows.filter((row) => row.campaign_type === 'catalog').length;

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
                    <div className="flex flex-col gap-3 rounded-md border bg-card p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-foreground">Campaign operations</h2>
                            <p className="text-sm text-muted-foreground">Launch catalog campaigns or promo codes from a controlled modal workflow with targeting and schedule rules.</p>
                        </div>
                        <DialogTrigger asChild>
                            <Button className="gap-2">
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

                <Card>
                    <CardHeader>
                        <CardTitle>Campaign command center</CardTitle>
                        <CardDescription>Live, scheduled, expired, and paused campaigns with scope, priority, budget guardrails, and quick operations.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {rows.length === 0 ? (
                            <div className="rounded-md border border-dashed p-8 text-sm text-muted-foreground">
                                No campaigns yet. Create a catalog campaign or promo code to start merchandising.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full min-w-[1100px] text-left text-sm">
                                    <thead className="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3">Campaign</th>
                                            <th className="px-4 py-3">Type</th>
                                            <th className="px-4 py-3">Discount</th>
                                            <th className="px-4 py-3">Audience</th>
                                            <th className="px-4 py-3">Schedule</th>
                                            <th className="px-4 py-3">Usage</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => {
                                            const active = Boolean(row.is_active);
                                            const state = statusFor(row);
                                            return (
                                                <tr key={row.id} className="border-t align-top">
                                                    <td className="px-4 py-4">
                                                        <div className="font-semibold text-foreground">{row.title}</div>
                                                        <div className="mt-1 flex flex-wrap gap-2">
                                                            <Badge variant="outline">{row.code}</Badge>
                                                            {row.badge ? <Badge>{row.badge}</Badge> : null}
                                                            {row.marketing_channel ? <Badge variant="secondary">{row.marketing_channel}</Badge> : null}
                                                        </div>
                                                        <p className="mt-2 max-w-md text-xs text-muted-foreground">{row.description || 'No brief added.'}</p>
                                                    </td>
                                                    <td className="px-4 py-4">
                                                        <div className="capitalize">{row.campaign_type || 'coupon'}</div>
                                                        <div className="mt-1 text-xs text-muted-foreground">Priority {row.priority ?? 100}</div>
                                                    </td>
                                                    <td className="px-4 py-4 font-semibold">
                                                        {discountLabel(row)}
                                                        {row.max_discount_amount ? <div className="mt-1 text-xs font-normal text-muted-foreground">Cap {row.currency} {money(row.max_discount_amount)}</div> : null}
                                                        <div className="mt-1 text-xs font-normal text-muted-foreground">Min {row.currency} {money(row.min_spend)}</div>
                                                    </td>
                                                    <td className="px-4 py-4">
                                                        <div className="flex items-center gap-2 capitalize">
                                                            <Target className="h-4 w-4 text-muted-foreground" />
                                                            {String(row.scope_type || 'all').replace('_', ' ')}
                                                        </div>
                                                        <div className="mt-1 text-xs text-muted-foreground">
                                                            Products {(row.target_product_ids || []).length} · Sellers {(row.target_seller_profile_ids || []).length} · Categories {(row.target_category_ids || []).length}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        <div>{fmtDate(row.starts_at)}</div>
                                                        <div className="mt-1">{fmtDate(row.ends_at)}</div>
                                                        {row.daily_start_time || row.daily_end_time ? (
                                                            <div className="mt-1 text-xs font-semibold text-foreground">
                                                                Daily {row.daily_start_time || '00:00'} - {row.daily_end_time || '23:59'}
                                                            </div>
                                                        ) : null}
                                                    </td>
                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {row.used_count ?? 0}
                                                        {row.usage_limit ? ` / ${row.usage_limit}` : ' / Unlimited'}
                                                    </td>
                                                    <td className="px-4 py-4">
                                                        <StatusBadge status={state} />
                                                    </td>
                                                    <td className="px-4 py-4">
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
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
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

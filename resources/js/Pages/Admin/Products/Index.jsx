import { Form, Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { AdminFilterBar } from '@/components/admin/AdminFilterBar';
import { AdminPagination } from '@/components/admin/AdminPagination';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { DateTimeField, EnterpriseSelect, Field, FileUploadField, SectionHeader } from '@/components/admin/EnterpriseForm';
import { StatCard } from '@/components/admin/StatCard';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { BadgePercent, Boxes, Download, Eye, ImageIcon, Package, PackagePlus, ShieldCheck, Truck, Zap } from 'lucide-react';

function fmtDate(iso) {
    if (!iso || iso === '—') return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

function ProductTypeBadge({ type, label, hint }) {
    const normalized = String(type || '').toLowerCase();
    const isInstantDelivery = String(label || '').toLowerCase().includes('instant');
    const Icon = normalized === 'physical' ? Truck : isInstantDelivery ? Zap : normalized === 'service' ? Package : Download;
    const variant = normalized === 'physical' ? 'outline' : normalized === 'digital' ? 'success' : 'secondary';

    return (
        <div className="flex min-w-40 items-center gap-2">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-border/70 bg-background text-muted-foreground">
                <Icon className="h-4 w-4" />
            </span>
            <div className="min-w-0">
                <Badge variant={variant} className="capitalize">
                    {label || type || '—'}
                </Badge>
                <p className="mt-1 max-w-48 truncate text-xs text-muted-foreground" title={hint || ''}>
                    {hint || 'Product fulfillment type'}
                </p>
            </div>
        </div>
    );
}

export default function ProductsIndex({
    header,
    rows,
    pagination,
    filters,
    index_url,
    store_url,
    bulk_discount_url,
    seller_options,
    category_options,
    status_options,
    create_type_options,
    filter_type_options,
    summary,
    bulk_moderate_url,
}) {
    const f = filters || {};
    const status = f.status ?? '';
    const type = f.type ?? '';
    const [selected, setSelected] = useState(() => new Set());
    const [bulkStatus, setBulkStatus] = useState('inactive');
    const [discountValue, setDiscountValue] = useState('10');
    const [discountLabel, setDiscountLabel] = useState('Birthday offer');
    const [selectAllFiltered, setSelectAllFiltered] = useState(false);
    const [createProductType, setCreateProductType] = useState('physical');
    const [createInstantDelivery, setCreateInstantDelivery] = useState(false);
    const visibleIds = useMemo(() => rows.map((r) => Number(r.row_id)).filter(Boolean), [rows]);
    const allChecked = visibleIds.length > 0 && visibleIds.every((id) => selected.has(id));
    const tableRows = useMemo(
        () => rows.map((r) => ({
            ...r,
            Select: String(r.row_id ?? ''),
            Product: r.title ?? '—',
            Type: r.type ?? '—',
            Status: r.status ?? '—',
            Seller: r.seller ?? '—',
            Price: r.price ?? '—',
            Discount: r.campaign ? `${r.campaign.discount_percentage}% - ${r.campaign.badge || r.campaign.title}` : (r.discount ? `${r.discount}%${r.discount_label ? ` - ${r.discount_label}` : ''}` : '—'),
            Ops: r.ops ?? '—',
            Updated: r.updated ?? '—',
            Details: r.details_href ?? r.href ?? '',
        })),
        [rows],
    );

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
        const scopeLabel = selectAllFiltered ? 'all filtered products' : `${ids.length} selected products`;
        if (!window.confirm(`Apply bulk moderation to ${scopeLabel}?`)) return;
        router.post(
            bulk_moderate_url,
            {
                ids,
                select_all: selectAllFiltered,
                filters: { q: f.q ?? '', status: f.status ?? '', type: f.type ?? '' },
                status: bulkStatus,
                reason: 'bulk_moderation',
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

    const applyBulkDiscount = () => {
        const ids = Array.from(selected);
        if (!ids.length && !selectAllFiltered) return;
        const scopeLabel = selectAllFiltered ? 'all filtered products' : `${ids.length} selected products`;
        if (!window.confirm(`Apply ${discountValue || 0}% discount to ${scopeLabel}?`)) return;
        router.post(
            bulk_discount_url,
            {
                ids,
                select_all: selectAllFiltered,
                filters: { q: f.q ?? '', status: f.status ?? '', type: f.type ?? '' },
                discount_percentage: discountValue,
                discount_label: discountLabel,
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
                <Dialog>
                    <div className="flex flex-col gap-3 rounded-md border bg-card p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-foreground">Product operations</h2>
                            <p className="text-sm text-muted-foreground">Create seller listings from a governed modal workflow, then manage pricing, status, and catalog readiness from the table.</p>
                        </div>
                        <DialogTrigger asChild>
                            <Button className="gap-2">
                                <PackagePlus className="h-4 w-4" />
                                New product
                            </Button>
                        </DialogTrigger>
                    </div>
                    <DialogContent className="max-h-[92vh] max-w-6xl overflow-y-auto p-0">
                        <DialogHeader className="border-b bg-muted/20 px-6 py-5 text-left">
                            <DialogTitle>Create seller product</DialogTitle>
                            <DialogDescription>
                                Controlled listing workflow with seller assignment, media, merchandising, stock, and operational metadata.
                            </DialogDescription>
                        </DialogHeader>
                    <CardHeader className="hidden border-b bg-muted/20">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <CardTitle>Create seller product</CardTitle>
                                <CardDescription>
                                    Controlled listing workflow with seller assignment, media, merchandising, stock, and operational metadata.
                                </CardDescription>
                            </div>
                            <Badge variant="outline" className="w-fit gap-2">
                                <ShieldCheck className="h-3.5 w-3.5" />
                                Admin governed
                            </Badge>
                        </div>
                    </CardHeader>
                    <div className="px-6 pb-6">
                        <Form action={store_url} method="post" encType="multipart/form-data" className="space-y-6 pt-6">
                            <section className="grid gap-4 rounded-md border bg-background p-4 xl:grid-cols-4">
                                <SectionHeader icon={PackagePlus} title="Listing identity" description="Seller ownership, taxonomy, customer-visible title, and fulfillment model." className="xl:col-span-4" />
                                <Field label="Seller" required className="xl:col-span-2">
                                    <EnterpriseSelect
                                        name="seller_profile_id"
                                        required
                                        placeholder="Choose seller"
                                        options={(seller_options || []).map((seller) => ({
                                            ...seller,
                                            label: `${seller.label} - ${seller.verification}/${seller.status}`,
                                        }))}
                                    />
                                </Field>
                                <Field label="Category" required>
                                    <EnterpriseSelect name="category_id" required placeholder="Choose category" options={category_options || []} />
                                </Field>
                                <Field label="Fulfillment" required>
                                    <EnterpriseSelect
                                        name="product_type"
                                        required
                                        value={createProductType}
                                        onChange={(e) => {
                                            const nextType = e.target.value;
                                            setCreateProductType(nextType);
                                            if (nextType !== 'digital') {
                                                setCreateInstantDelivery(false);
                                            }
                                        }}
                                        placeholder={null}
                                        options={create_type_options || []}
                                    />
                                </Field>
                                {createProductType === 'digital' ? (
                                    <Field label="Digital fulfillment" className="xl:col-span-2">
                                        <input type="hidden" name="is_instant_delivery" value="0" />
                                        <label className="flex min-h-11 items-center gap-3 rounded-md border border-border/70 bg-muted/20 px-3 text-sm font-medium text-foreground">
                                            <input
                                                type="checkbox"
                                                name="is_instant_delivery"
                                                value="1"
                                                checked={createInstantDelivery}
                                                onChange={(e) => setCreateInstantDelivery(e.target.checked)}
                                            />
                                            Instant delivery
                                        </label>
                                    </Field>
                                ) : (
                                    <input type="hidden" name="is_instant_delivery" value="0" />
                                )}
                                <Field label="Title" required className="xl:col-span-2">
                                <Input name="title" required placeholder="Professional product title" />
                                </Field>
                                <Field label="Status">
                                    <EnterpriseSelect name="status" defaultValue="draft" placeholder={null} options={status_options || []} />
                                </Field>
                                <Field label="Currency" required>
                                    <Input name="currency" defaultValue="BDT" maxLength={3} required />
                                </Field>
                            </section>

                            <section className="grid gap-4 rounded-md border bg-background p-4 xl:grid-cols-4">
                                <SectionHeader icon={BadgePercent} title="Pricing and merchandising" description="Manual product discount is optional; scheduled campaigns can override it dynamically." className="xl:col-span-4" />
                                <Field label="Price" required>
                                <Input name="base_price" type="number" min="0" step="0.01" required placeholder="0.00" />
                                </Field>
                                <Field label="Manual discount %" hint="Campaigns recommended">
                                <Input name="discount_percentage" type="number" min="0" max="95" step="0.01" defaultValue="0" />
                                </Field>
                                <Field label="Discount label" className="xl:col-span-2">
                                <Input name="discount_label" placeholder="Birthday offer, Eid sale, clearance" />
                                </Field>
                            </section>

                            <section className="grid gap-4 rounded-md border bg-background p-4 xl:grid-cols-4">
                                <SectionHeader icon={Boxes} title="Inventory, media, and product details" description="Upload product assets and operational attributes without exposing storage paths." className="xl:col-span-4" />
                                <Field label="Stock">
                                <Input name="stock" type="number" min="0" defaultValue="0" />
                                </Field>
                                <Field label="Launch window" hint="Optional">
                                    <DateTimeField name="launch_at" />
                                </Field>
                                <Field label="Primary image">
                                    <FileUploadField name="primary_image" />
                                </Field>
                                <Field label="Gallery images">
                                    <FileUploadField name="gallery_images[]" multiple />
                                </Field>
                                <div className="grid gap-3 xl:col-span-2 xl:grid-cols-2">
                                <Input name="brand" placeholder="Brand" />
                                <Input name="condition" placeholder="Condition" defaultValue="New" />
                                <Input name="warranty_status" placeholder="Warranty status" />
                                <Input name="product_location" placeholder="Product location" />
                                <Input name="tags" className="xl:col-span-2" placeholder="Tags comma separated, e.g. premium, escrow, verified" />
                                </div>
                                <Field label="Description" className="xl:col-span-4">
                                <Textarea name="description" rows={5} placeholder="Detailed product description, delivery terms, inclusions, and buyer-facing notes" />
                                </Field>
                            </section>
                            <div className="flex justify-end">
                                <Button type="submit" className="gap-2">
                                    <PackagePlus className="h-4 w-4" />
                                    Create product for seller
                                </Button>
                            </div>
                        </Form>
                    </div>
                    </DialogContent>
                </Dialog>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <StatCard label="Published" value={String(summary?.published ?? 0)} />
                    <StatCard label="Draft" value={String(summary?.draft ?? 0)} />
                    <StatCard label="Inactive" value={String(summary?.inactive ?? 0)} />
                    <StatCard label="Physical" value={String(summary?.physical ?? 0)} />
                    <StatCard label="Digital" value={String(summary?.digital ?? 0)} />
                    <StatCard label="Needs attention" value={String(summary?.needs_attention ?? 0)} />
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
                    <div className="w-full sm:w-56">
                        <p className="mb-1 text-xs font-medium text-muted-foreground">Product type</p>
                        <Select
                            value={type || 'all'}
                            onValueChange={(v) => {
                                const next = { ...f, page: '1' };
                                if (v === 'all') delete next.type;
                                else next.type = v;
                                router.get(index_url, next, { preserveState: true, replace: true });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                {(filter_type_options || []).map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="rounded-lg border border-border/70 bg-card p-3">
                    <div className="grid gap-3 xl:grid-cols-[1fr_1fr]">
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
                            <EnterpriseSelect
                                value={bulkStatus}
                                onChange={(e) => setBulkStatus(e.target.value)}
                                placeholder={null}
                                className="w-40"
                                options={[
                                    { value: 'draft', label: 'Draft' },
                                    { value: 'active', label: 'Active' },
                                    { value: 'inactive', label: 'Inactive' },
                                    { value: 'archived', label: 'Archived' },
                                    { value: 'published', label: 'Published' },
                                ]}
                            />
                            <Button type="button" size="sm" onClick={applyBulk} disabled={selected.size === 0 && !selectAllFiltered}>
                                {selectAllFiltered ? 'Moderate all filtered' : `Moderate ${selected.size} selected`}
                            </Button>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 xl:justify-end">
                            <Input className="h-9 w-36" type="number" min="0" max="95" step="0.01" value={discountValue} onChange={(e) => setDiscountValue(e.target.value)} />
                            <Input className="h-9 w-52" value={discountLabel} onChange={(e) => setDiscountLabel(e.target.value)} placeholder="Campaign name" />
                            <Button type="button" size="sm" variant="outline" onClick={applyBulkDiscount} disabled={selected.size === 0 && !selectAllFiltered}>
                                Apply campaign discount
                            </Button>
                        </div>
                    </div>
                </div>

                <DataTableShell
                    columns={['Select', 'Product', 'Type', 'Status', 'Seller', 'Price', 'Discount', 'Ops', 'Updated', 'Details']}
                    rows={tableRows}
                    emptyTitle="No products"
                    renderers={{
                        Select: (_value, row) => {
                            const id = Number(row.row_id);
                            return (
                                <input
                                    type="checkbox"
                                    checked={selected.has(id)}
                                    onChange={(e) => toggleOne(id, e.target.checked)}
                                />
                            );
                        },
                        Product: (_value, row) => (
                            <div className="flex min-w-72 items-center gap-3">
                                <Link href={row.href} className="block h-14 w-14 shrink-0 overflow-hidden rounded-md border border-border/70 bg-muted/30">
                                    {row.thumbnail_url ? (
                                        <img src={row.thumbnail_url} alt={row.title || 'Product image'} className="h-full w-full object-cover" />
                                    ) : (
                                        <span className="flex h-full w-full items-center justify-center text-muted-foreground">
                                            <ImageIcon className="h-5 w-5" />
                                        </span>
                                    )}
                                </Link>
                                <div className="min-w-0">
                                    <Link href={row.href} className="font-semibold text-foreground hover:text-primary hover:underline">
                                        {row.title || '—'}
                                    </Link>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {row.sku} - {row.price || '—'}
                                    </p>
                                </div>
                            </div>
                        ),
                        Type: (_value, row) => <ProductTypeBadge type={row.type} label={row.type_label} hint={row.type_hint} />,
                        Status: (value) => <StatusBadge status={String(value)} />,
                        Seller: (value) => <span className="text-muted-foreground">{String(value)}</span>,
                        Price: (value) => <span className="font-medium tabular-nums">{String(value)}</span>,
                        Discount: (value) => <span className="font-medium text-emerald-700">{String(value)}</span>,
                        Ops: (value) => <StatusBadge status={String(value)} />,
                        Updated: (value) => <span className="text-muted-foreground">{fmtDate(value)}</span>,
                        Details: (_value, row) => (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={row.details_href || row.href}>
                                    <Eye className="h-4 w-4" />
                                    View details
                                </Link>
                            </Button>
                        ),
                    }}
                />
                <AdminPagination baseUrl={index_url} pagination={pagination} extraParams={f} />
            </div>
        </AdminLayout>
    );
}

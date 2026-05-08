import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Box, Download, Eye, ImageIcon, Layers, Package, Star, Truck, Zap } from 'lucide-react';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

function ProductTypeBadge({ product }) {
    const normalized = String(product?.type || '').toLowerCase();
    const Icon = normalized === 'physical' ? Truck : normalized === 'instant_delivery' ? Zap : normalized === 'service' ? Package : Download;
    const variant = normalized === 'physical' ? 'outline' : normalized === 'digital' || normalized === 'instant_delivery' ? 'success' : 'secondary';

    return (
        <div className="flex items-start gap-3 rounded-lg border border-border/70 bg-background p-4">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-border/70 bg-muted/30 text-muted-foreground">
                <Icon className="h-5 w-5" />
            </span>
            <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Fulfillment type</p>
                <div className="mt-1">
                    <Badge variant={variant}>{product?.type_label || product?.type || '—'}</Badge>
                </div>
                <p className="mt-2 text-sm text-muted-foreground">{product?.type_hint || 'Product fulfillment type'}</p>
            </div>
        </div>
    );
}

function DetailItem({ label, value }) {
    return (
        <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 break-words font-medium text-foreground">{value || '—'}</p>
        </div>
    );
}

function EmptyPanel({ title, description }) {
    return (
        <div className="rounded-lg border border-dashed border-border/80 bg-muted/20 px-4 py-6 text-center">
            <p className="text-sm font-medium text-foreground">{title}</p>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

function MetricTile({ label, value, icon: Icon }) {
    return (
        <div className="rounded-lg border border-border/70 bg-background p-4">
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">{label}</p>
                <Icon className="h-4 w-4 text-muted-foreground" />
            </div>
            <p className="mt-2 text-2xl font-semibold text-foreground">{value}</p>
        </div>
    );
}

function JsonBlock({ value }) {
    return (
        <pre className="max-h-80 overflow-auto rounded-lg border border-border/70 bg-muted/30 p-3 text-xs text-muted-foreground">
            {JSON.stringify(value || {}, null, 2)}
        </pre>
    );
}

export default function ProductShow({
    header,
    product,
    can_moderate,
    moderate_url,
    list_href,
    ops_metrics,
    inventory_summary,
    inventory_records,
    variants,
    recent_order_items,
    recent_reviews,
    quality_checks,
    moderation_reason_options,
    pending_approvals,
    timeline,
}) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};
    const gallery = product.images || [];
    const [activeImage, setActiveImage] = useState(() => gallery[0]?.url || product.image_url || '');
    const [detailsView, setDetailsView] = useState('attributes');
    const primaryImage = activeImage || gallery[0]?.url || product.image_url || '';
    const attributeRows = product.attribute_rows || [];
    const fulfillmentStats = useMemo(
        () => [
            { label: 'Available', value: String(inventory_summary?.available ?? 0), icon: Box },
            { label: 'On hand', value: String(inventory_summary?.on_hand ?? 0), icon: Package },
            { label: 'Reserved', value: String(inventory_summary?.reserved ?? 0), icon: Layers },
            { label: 'Sold', value: String(inventory_summary?.sold ?? 0), icon: Truck },
        ],
        [inventory_summary],
    );

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>Back to products</Link>
                </Button>
                <Button variant="outline" size="sm" asChild>
                    <Link href={product.public_href}>
                        <Eye className="h-4 w-4" />
                        View storefront page
                    </Link>
                </Button>
            </div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.status ? <p className="mb-4 text-sm text-destructive">{errors.status}</p> : null}
            {errors.reason ? <p className="mb-4 text-sm text-destructive">{errors.reason}</p> : null}
            {errors.policy_code ? <p className="mb-4 text-sm text-destructive">{errors.policy_code}</p> : null}
            {errors.evidence_notes ? <p className="mb-4 text-sm text-destructive">{errors.evidence_notes}</p> : null}

            <div className="grid gap-6 xl:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Product media</CardTitle>
                        <CardDescription>Primary image and gallery assets from the seller listing.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="overflow-hidden rounded-lg border border-border/70 bg-muted/20">
                            {primaryImage ? (
                                <img src={primaryImage} alt={product.title || 'Product image'} className="aspect-[4/3] w-full object-cover" />
                            ) : (
                                <div className="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                    <ImageIcon className="h-10 w-10" />
                                    <p className="text-sm">No product image uploaded</p>
                                </div>
                            )}
                        </div>
                        {gallery.length ? (
                            <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                                {gallery.map((image) => (
                                    <button
                                        key={image.raw}
                                        type="button"
                                        onClick={() => setActiveImage(image.url)}
                                        className={`overflow-hidden rounded-md border bg-background ${activeImage === image.url ? 'border-primary ring-2 ring-primary/20' : 'border-border/70'}`}
                                        title={image.raw}
                                    >
                                        <img src={image.url} alt="" className="aspect-square w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Listing details</CardTitle>
                        <CardDescription>Catalog identity, value, and publication state.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Product</p>
                                <h2 className="mt-1 text-2xl font-semibold leading-tight text-foreground">{product.title ?? 'Untitled product'}</h2>
                                <p className="mt-1 text-sm text-muted-foreground">#{product.id}{product.uuid ? ` - ${product.uuid}` : ''}</p>
                            </div>
                            <StatusBadge status={product.status} />
                        </div>
                        <ProductTypeBadge product={product} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <DetailItem label="Price" value={product.price} />
                            <DetailItem label="Discount" value={product.active_campaign ? `${product.active_campaign.discount_percentage}% - ${product.active_campaign.badge || product.active_campaign.title}` : (Number(product.discount_percentage || 0) > 0 ? `${Number(product.discount_percentage || 0).toLocaleString()}%${product.discount_label ? ` - ${product.discount_label}` : ''}` : '—')} />
                            <DetailItem label="Category" value={product.category} />
                            <DetailItem label="Published" value={fmtDate(product.published_at)} />
                            <DetailItem label="Last updated" value={fmtDate(product.updated_at)} />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <DetailItem label="Seller" value={product.seller} />
                            <DetailItem label="Seller status" value={product.seller_verification_status || product.seller_store_status} />
                            <DetailItem label="Storefront" value={product.storefront} />
                            <DetailItem label="Storefront visibility" value={product.storefront_public ? 'Public' : 'Private'} />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {fulfillmentStats.map((item) => (
                    <MetricTile key={item.label} {...item} />
                ))}
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                <MetricTile label="Order line items" value={String(ops_metrics?.total_order_items ?? 0)} icon={Package} />
                <MetricTile label="Open disputes" value={String(ops_metrics?.open_disputes ?? 0)} icon={Zap} />
                <MetricTile label="Avg rating" value={String(ops_metrics?.avg_rating ?? 0)} icon={Star} />
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Product data</CardTitle>
                            <CardDescription>Attributes, variants, inventory, and recent commerce activity.</CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {[
                                ['attributes', 'Attributes'],
                                ['variants', 'Variants'],
                                ['inventory', 'Inventory'],
                                ['orders', 'Orders'],
                                ['reviews', 'Reviews'],
                                ['raw', 'Raw JSON'],
                            ].map(([value, label]) => (
                                <Button
                                    key={value}
                                    type="button"
                                    size="sm"
                                    variant={detailsView === value ? 'default' : 'outline'}
                                    onClick={() => setDetailsView(value)}
                                >
                                    {label}
                                </Button>
                            ))}
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {detailsView === 'attributes' ? (
                        attributeRows.length ? (
                            <Table>
                                <TableHeader><TableRow><TableHead>Attribute</TableHead><TableHead>Value</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {attributeRows.map((row) => (
                                        <TableRow key={row.key}>
                                            <TableCell className="font-medium">{row.label}</TableCell>
                                            <TableCell className="text-muted-foreground">{row.value}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : <EmptyPanel title="No attributes" description="Seller has not added extra product metadata yet." />
                    ) : null}
                    {detailsView === 'variants' ? (
                        variants?.length ? (
                            <Table>
                                <TableHeader><TableRow><TableHead>Variant</TableHead><TableHead>SKU</TableHead><TableHead>Price delta</TableHead><TableHead>Stock</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {variants.map((variant) => (
                                        <TableRow key={variant.id}>
                                            <TableCell className="font-medium">{variant.title || `Variant #${variant.id}`}</TableCell>
                                            <TableCell>{variant.sku || '—'}</TableCell>
                                            <TableCell>{variant.price_delta}</TableCell>
                                            <TableCell>{variant.stock_on_hand} on hand / {variant.stock_sold} sold</TableCell>
                                            <TableCell><StatusBadge status={variant.is_active ? 'active' : 'inactive'} /></TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : <EmptyPanel title="No variants" description="This product currently uses the base listing only." />
                    ) : null}
                    {detailsView === 'inventory' ? (
                        inventory_records?.length ? (
                            <Table>
                                <TableHeader><TableRow><TableHead>Scope</TableHead><TableHead>Available</TableHead><TableHead>On hand</TableHead><TableHead>Reserved</TableHead><TableHead>Sold</TableHead><TableHead>Updated</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {inventory_records.map((record) => (
                                        <TableRow key={record.id}>
                                            <TableCell className="font-medium">{record.variant}</TableCell>
                                            <TableCell>{record.available}</TableCell>
                                            <TableCell>{record.on_hand}</TableCell>
                                            <TableCell>{record.reserved}</TableCell>
                                            <TableCell>{record.sold}</TableCell>
                                            <TableCell className="text-muted-foreground">{fmtDate(record.updated_at)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : <EmptyPanel title="No inventory records" description="Stock has not been initialized for this listing." />
                    ) : null}
                    {detailsView === 'orders' ? (
                        recent_order_items?.length ? (
                            <Table>
                                <TableHeader><TableRow><TableHead>Order</TableHead><TableHead>Quantity</TableHead><TableHead>Line total</TableHead><TableHead>Delivery</TableHead><TableHead>Status</TableHead><TableHead>Placed</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {recent_order_items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.order_href ? <Link href={item.order_href} className="text-primary hover:underline">{item.order_number}</Link> : item.order_number}
                                            </TableCell>
                                            <TableCell>{item.quantity}</TableCell>
                                            <TableCell>{item.line_total}</TableCell>
                                            <TableCell>{item.delivery_state}</TableCell>
                                            <TableCell><StatusBadge status={item.status} /></TableCell>
                                            <TableCell className="text-muted-foreground">{fmtDate(item.placed_at)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : <EmptyPanel title="No orders yet" description="Order activity will appear here after buyers purchase this product." />
                    ) : null}
                    {detailsView === 'reviews' ? (
                        recent_reviews?.length ? (
                            <div className="grid gap-3 md:grid-cols-2">
                                {recent_reviews.map((review) => (
                                    <div key={review.id} className="rounded-lg border border-border/70 bg-background p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium">{review.buyer}</p>
                                                <p className="text-xs text-muted-foreground">{fmtDate(review.created_at)}</p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">{review.rating}/5</Badge>
                                                <StatusBadge status={review.status} />
                                            </div>
                                        </div>
                                        <p className="mt-3 text-sm text-muted-foreground">{review.comment || 'No written comment.'}</p>
                                    </div>
                                ))}
                            </div>
                        ) : <EmptyPanel title="No reviews yet" description="Visible and pending reviews will be listed here." />
                    ) : null}
                    {detailsView === 'raw' ? <JsonBlock value={product.attributes} /> : null}
                </CardContent>
            </Card>

            {product.description ? (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Description</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="whitespace-pre-wrap text-sm text-muted-foreground">{product.description}</p>
                    </CardContent>
                </Card>
            ) : null}

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Quality checks</CardTitle>
                    <CardDescription>Signals admins can use before moderation decisions.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    {(quality_checks || []).map((c) => (
                        <div key={c.label} className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                            <span>{c.label}</span>
                            <StatusBadge status={c.ok ? 'completed' : 'needs_attention'} />
                        </div>
                    ))}
                </CardContent>
            </Card>

            {can_moderate ? (
                <Card className="mt-6 border-amber-200/70 bg-amber-50/40">
                    <CardHeader>
                        <CardTitle>Moderation controls</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form action={moderate_url} method="post" className="grid gap-3 md:grid-cols-3">
                            <div>
                                <label htmlFor="status" className="mb-1 block text-sm font-medium">
                                    New status
                                </label>
                                <select id="status" name="status" defaultValue={product.status} className="h-10 w-full rounded-md border px-3 text-sm">
                                    <option value="draft">draft</option>
                                    <option value="active">active</option>
                                    <option value="inactive">inactive</option>
                                    <option value="archived">archived</option>
                                    <option value="published">published</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <label htmlFor="policy_code" className="mb-1 block text-sm font-medium">
                                    Policy code
                                </label>
                                <select id="policy_code" name="policy_code" className="h-10 w-full rounded-md border px-3 text-sm">
                                    {(moderation_reason_options || []).map((o) => (
                                        <option key={o} value={o}>{o}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-3">
                                <label htmlFor="reason" className="mb-1 block text-sm font-medium">
                                    Reason (required, audit)
                                </label>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <input id="reason" name="reason" className="h-10 w-full rounded-md border px-3 text-sm" placeholder="Human-readable moderation rationale" />
                                    <select
                                        onChange={(e) => {
                                            const input = document.getElementById('reason');
                                            if (input && e.target.value) input.value = e.target.value;
                                        }}
                                        className="h-10 w-full rounded-md border px-3 text-sm"
                                        defaultValue=""
                                    >
                                        <option value="">Reason templates</option>
                                        {(moderation_reason_options || []).map((o) => (
                                            <option key={o} value={o}>{o}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="md:col-span-3">
                                <label htmlFor="evidence_notes" className="mb-1 block text-sm font-medium">
                                    Evidence notes (required for policy/counterfeit)
                                </label>
                                <textarea id="evidence_notes" name="evidence_notes" rows={3} className="w-full rounded-md border px-3 py-2 text-sm" placeholder="Attach concise evidence summary and references." />
                            </div>
                            <div className="md:col-span-3">
                                <Button type="submit">Apply moderation update</Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}

            <Card className="mt-6">
                <CardHeader><CardTitle>Pending approvals</CardTitle></CardHeader>
                <CardContent>
                    {!pending_approvals?.length ? <p className="text-sm text-muted-foreground">No pending approvals.</p> : pending_approvals.map((a) => (
                        <Form key={a.id} action={a.decision_url} method="post" className="mb-3 rounded-md border p-3">
                            <input type="hidden" name="decision" value="approve" />
                            <p className="text-sm font-medium">{a.action_code}</p>
                            <p className="text-xs text-muted-foreground">{a.reason_code} · requested by {a.requested_by}</p>
                            <div className="mt-2 flex gap-2">
                                <input name="decision_reason" className="h-9 flex-1 rounded-md border px-2 text-sm" placeholder="Approval reason" />
                                <Button size="sm" type="submit">Approve</Button>
                            </div>
                        </Form>
                    ))}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader><CardTitle>Change timeline</CardTitle></CardHeader>
                <CardContent>
                    {!timeline?.length ? <p className="text-sm text-muted-foreground">No timeline entries.</p> : (
                        <div className="space-y-2">
                            {timeline.map((t) => (
                                <div key={t.id} className="rounded-md border p-3 text-sm">
                                    <p className="font-mono text-xs">{t.action}</p>
                                    <p className="text-muted-foreground">{t.actor} · {t.created_at ? new Date(t.created_at).toLocaleString() : '—'}</p>
                                    <p>{t.reason_code ?? '—'}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}

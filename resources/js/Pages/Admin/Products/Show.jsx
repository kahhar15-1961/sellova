import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function ProductShow({ header, product, can_moderate, moderate_url, list_href, ops_metrics, quality_checks, moderation_reason_options, pending_approvals, timeline }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Products</Link>
                </Button>
            </div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.status ? <p className="mb-4 text-sm text-destructive">{errors.status}</p> : null}
            {errors.reason ? <p className="mb-4 text-sm text-destructive">{errors.reason}</p> : null}
            {errors.policy_code ? <p className="mb-4 text-sm text-destructive">{errors.policy_code}</p> : null}
            {errors.evidence_notes ? <p className="mb-4 text-sm text-destructive">{errors.evidence_notes}</p> : null}

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Listing</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Title: {product.title ?? '—'}</p>
                        <p>
                            Status: <StatusBadge status={product.status} />
                        </p>
                        <p>Price: {product.price}</p>
                        <p>Type: {product.type}</p>
                        <p>Published at: {product.published_at ?? '—'}</p>
                        <p>Updated at: {product.updated_at ?? '—'}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Ownership</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Seller: {product.seller ?? '—'}</p>
                        <p>Storefront: {product.storefront ?? '—'}</p>
                        <p>Category: {product.category ?? '—'}</p>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                <Card><CardHeader><CardTitle className="text-sm">Order line items</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{ops_metrics?.total_order_items ?? 0}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Open disputes</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{ops_metrics?.open_disputes ?? 0}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Avg rating</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{String(ops_metrics?.avg_rating ?? 0)}</CardContent></Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Quality checks</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-2 sm:grid-cols-2">
                    {(quality_checks || []).map((c) => (
                        <div key={c.label} className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                            <span>{c.label}</span>
                            <StatusBadge status={c.ok ? 'completed' : 'needs_attention'} />
                        </div>
                    ))}
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

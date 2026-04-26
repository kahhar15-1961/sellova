import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

function fmtDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); } catch { return String(iso); }
}

export default function SellerProfileShow({ header, seller, stats, recent_products, list_href, state_update_url, reason_codes, pending_approvals, timeline }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4"><Button variant="outline" size="sm" asChild><Link href={list_href}>← Seller Profiles</Link></Button></div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.reason ? <p className="mb-4 text-sm text-destructive">{errors.reason}</p> : null}

            <div className="grid gap-4 sm:grid-cols-3">
                <Card><CardHeader><CardTitle className="text-sm">Products</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.products_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Pending withdrawals</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.pending_withdrawals}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Open disputes</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.open_disputes}</CardContent></Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Seller profile</CardTitle></CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Display name: {seller.display_name ?? '—'}</p>
                        <p>Legal name: {seller.legal_name ?? '—'}</p>
                        <p>Country: {seller.country_code ?? '—'}</p>
                        <p>Default currency: {seller.default_currency ?? '—'}</p>
                        <p>Verification: <StatusBadge status={seller.verification_status} /></p>
                        <p>Store status: <StatusBadge status={seller.store_status} /></p>
                        <p>Created: {fmtDate(seller.created_at)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Linked account</CardTitle></CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {!seller.account ? <p className="text-muted-foreground">No linked account</p> : (
                            <>
                                <p>Email: {seller.account.email ?? '—'}</p>
                                <p>Phone: {seller.account.phone ?? '—'}</p>
                                <p>Status: <StatusBadge status={seller.account.status} /></p>
                                <p>Risk level: {seller.account.risk_level}</p>
                            </>
                        )}
                        {seller.storefront ? (
                            <div className="mt-3 rounded-md border p-3">
                                <p className="text-xs text-muted-foreground">Storefront</p>
                                <p className="font-medium">{seller.storefront.title ?? '—'}</p>
                                <p className="text-sm text-muted-foreground">Status: {seller.storefront.status ?? '—'}</p>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6 border-amber-200/70 bg-amber-50/40">
                <CardHeader><CardTitle>Store state controls</CardTitle></CardHeader>
                <CardContent>
                    <Form action={state_update_url} method="post" className="grid gap-3 md:grid-cols-3">
                        <div>
                            <label htmlFor="store_status" className="mb-1 block text-sm font-medium">Store status</label>
                            <select id="store_status" name="store_status" defaultValue={seller.store_status} className="h-10 w-full rounded-md border px-3 text-sm">
                                <option value="active">active</option>
                                <option value="suspended">suspended</option>
                            </select>
                        </div>
                        <div className="md:col-span-2">
                            <label htmlFor="reason_code" className="mb-1 block text-sm font-medium">Reason code</label>
                            <select id="reason_code" name="reason_code" className="h-10 w-full rounded-md border px-3 text-sm">
                                {(reason_codes || []).map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </div>
                        <div className="md:col-span-3">
                            <label htmlFor="reason" className="mb-1 block text-sm font-medium">Reason details (required)</label>
                            <input id="reason" name="reason" className="h-10 w-full rounded-md border px-3 text-sm" placeholder="Detailed governance rationale" />
                        </div>
                        <div className="md:col-span-3">
                            <Button type="submit">Update seller store state</Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader><CardTitle>Pending approvals</CardTitle></CardHeader>
                <CardContent>
                    {!pending_approvals?.length ? <p className="text-sm text-muted-foreground">No pending approvals.</p> : pending_approvals.map((a) => (
                        <Form key={a.id} action={a.decision_url} method="post" className="mb-3 rounded-md border p-3">
                            <input type="hidden" name="decision" value="approve" />
                            <p className="text-sm font-medium">{a.action_code}</p>
                            <p className="text-xs text-muted-foreground">{a.reason_code} · requested by {a.requested_by} · {fmtDate(a.requested_at)}</p>
                            <div className="mt-2 flex gap-2">
                                <input name="decision_reason" className="h-9 flex-1 rounded-md border px-2 text-sm" placeholder="Approval reason" />
                                <Button size="sm" type="submit">Approve</Button>
                            </div>
                        </Form>
                    ))}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader><CardTitle>Recent products</CardTitle></CardHeader>
                <CardContent>
                    {!recent_products?.length ? <p className="text-sm text-muted-foreground">No products yet.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Product</TableHead><TableHead>Status</TableHead><TableHead>Price</TableHead><TableHead>Updated</TableHead></TableRow></TableHeader>
                            <TableBody>{recent_products.map((p) => <TableRow key={p.id}><TableCell><Link href={p.href} className="text-primary hover:underline">{p.title}</Link></TableCell><TableCell><StatusBadge status={p.status} /></TableCell><TableCell>{p.price}</TableCell><TableCell className="text-muted-foreground">{fmtDate(p.updated_at)}</TableCell></TableRow>)}</TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader><CardTitle>Change timeline</CardTitle></CardHeader>
                <CardContent>
                    {!timeline?.length ? <p className="text-sm text-muted-foreground">No timeline entries.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>When</TableHead><TableHead>Action</TableHead><TableHead>Actor</TableHead><TableHead>Reason</TableHead></TableRow></TableHeader>
                            <TableBody>{timeline.map((t) => <TableRow key={t.id}><TableCell className="text-muted-foreground">{fmtDate(t.created_at)}</TableCell><TableCell className="font-mono text-xs">{t.action}</TableCell><TableCell>{t.actor}</TableCell><TableCell>{t.reason_code ?? '—'}</TableCell></TableRow>)}</TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}

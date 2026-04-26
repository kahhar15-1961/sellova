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

export default function BuyerShow({ header, buyer, stats, wallets, recent_orders, list_href, risk_update_url, reason_codes, pending_approvals, timeline }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4"><Button variant="outline" size="sm" asChild><Link href={list_href}>← Buyers</Link></Button></div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.reason ? <p className="mb-4 text-sm text-destructive">{errors.reason}</p> : null}

            <div className="grid gap-4 sm:grid-cols-3">
                <Card><CardHeader><CardTitle className="text-sm">Orders</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.orders_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Wallets</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.wallets_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Open disputes</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.open_disputes}</CardContent></Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Buyer profile</CardTitle></CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Email: {buyer.email ?? '—'}</p>
                        <p>Phone: {buyer.phone ?? '—'}</p>
                        <p>Status: <StatusBadge status={buyer.status} /></p>
                        <p>Risk level: {buyer.risk_level}</p>
                        <p>Checkout restriction: {buyer.restricted_checkout ? 'Restricted' : 'Normal'}</p>
                        <p>Last login: {fmtDate(buyer.last_login_at)}</p>
                        <p>Created: {fmtDate(buyer.created_at)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Wallet footprint</CardTitle></CardHeader>
                    <CardContent>
                        {!wallets?.length ? <p className="text-sm text-muted-foreground">No wallets linked.</p> : (
                            <Table>
                                <TableHeader><TableRow><TableHead>ID</TableHead><TableHead>Type</TableHead><TableHead>Status</TableHead><TableHead>Currency</TableHead></TableRow></TableHeader>
                                <TableBody>{wallets.map((w) => <TableRow key={w.id}><TableCell><Link href={w.href} className="text-primary hover:underline">#{w.id}</Link></TableCell><TableCell>{w.type}</TableCell><TableCell><StatusBadge status={w.status} /></TableCell><TableCell>{w.currency ?? '—'}</TableCell></TableRow>)}</TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6 border-amber-200/70 bg-amber-50/40">
                <CardHeader><CardTitle>Fraud controls</CardTitle></CardHeader>
                <CardContent>
                    <Form action={risk_update_url} method="post" className="grid gap-3 md:grid-cols-4">
                        <div>
                            <label htmlFor="status" className="mb-1 block text-sm font-medium">Status</label>
                            <select id="status" name="status" defaultValue={buyer.status} className="h-10 w-full rounded-md border px-3 text-sm">
                                <option value="active">active</option>
                                <option value="suspended">suspended</option>
                                <option value="closed">closed</option>
                            </select>
                        </div>
                        <div>
                            <label htmlFor="risk_level" className="mb-1 block text-sm font-medium">Risk level</label>
                            <select id="risk_level" name="risk_level" defaultValue={buyer.risk_level} className="h-10 w-full rounded-md border px-3 text-sm">
                                <option value="low">low</option>
                                <option value="medium">medium</option>
                                <option value="high">high</option>
                            </select>
                        </div>
                        <div>
                            <label htmlFor="restricted_checkout" className="mb-1 block text-sm font-medium">Checkout</label>
                            <select id="restricted_checkout" name="restricted_checkout" defaultValue={buyer.restricted_checkout ? '1' : '0'} className="h-10 w-full rounded-md border px-3 text-sm">
                                <option value="0">allowed</option>
                                <option value="1">restricted</option>
                            </select>
                        </div>
                        <div>
                            <label htmlFor="reason_code" className="mb-1 block text-sm font-medium">Reason code</label>
                            <select id="reason_code" name="reason_code" className="h-10 w-full rounded-md border px-3 text-sm">
                                {(reason_codes || []).map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </div>
                        <div className="md:col-span-4">
                            <label htmlFor="reason" className="mb-1 block text-sm font-medium">Reason details (required)</label>
                            <input id="reason" name="reason" className="h-10 w-full rounded-md border px-3 text-sm" placeholder="Detailed rationale for governance record" />
                        </div>
                        <div className="md:col-span-4">
                            <Button type="submit">Apply fraud controls</Button>
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
                <CardHeader><CardTitle>Recent orders</CardTitle></CardHeader>
                <CardContent>
                    {!recent_orders?.length ? <p className="text-sm text-muted-foreground">No orders found.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Order</TableHead><TableHead>Status</TableHead><TableHead>Total</TableHead><TableHead>Placed</TableHead></TableRow></TableHeader>
                            <TableBody>{recent_orders.map((o) => <TableRow key={o.id}><TableCell>{o.order_number}</TableCell><TableCell><StatusBadge status={o.status} /></TableCell><TableCell>{o.total}</TableCell><TableCell className="text-muted-foreground">{fmtDate(o.placed_at)}</TableCell></TableRow>)}</TableBody>
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

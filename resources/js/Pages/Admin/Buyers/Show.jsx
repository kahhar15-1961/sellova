import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatMoney } from '@/lib/utils';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

export default function BuyerShow({ header, buyer, stats, wallets, payment_methods, reviews, recent_orders, list_href, risk_update_url, reason_codes, pending_approvals, timeline }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Buyers</Link>
                </Button>
            </div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.reason ? <p className="mb-4 text-sm text-destructive">{errors.reason}</p> : null}

            <div className="grid gap-4 sm:grid-cols-4">
                <Card><CardHeader><CardTitle className="text-sm">Orders</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.orders_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Wallets</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.wallets_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Payment methods</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.payment_methods_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Open disputes</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.open_disputes}</CardContent></Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Buyer profile</CardTitle>
                        <CardDescription>Identity, account health, and checkout posture</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Name: {buyer.name ?? `Buyer #${buyer.id}`}</p>
                        <p>Email: {buyer.email ?? '—'}</p>
                        <p>Phone: {buyer.phone ?? '—'}</p>
                        <p>Status: <StatusBadge status={buyer.status} /></p>
                        <p>Risk level: {buyer.risk_level}</p>
                        <p>Risk score: {buyer.risk_score ?? '—'} / 100</p>
                        <p>Risk band: {buyer.risk_band ?? '—'}</p>
                        <p>Checkout restriction: {buyer.restricted_checkout ? 'Restricted' : 'Normal'}</p>
                        <p>Last login: {fmtDate(buyer.last_login_at)}</p>
                        <p>Created: {fmtDate(buyer.created_at)}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Wallet footprint</CardTitle>
                        <CardDescription>Available and held balances per wallet</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!wallets?.length ? (
                            <p className="text-sm text-muted-foreground">No wallets linked.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Wallet</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Available</TableHead>
                                        <TableHead>Held</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {wallets.map((wallet) => (
                                        <TableRow key={wallet.id}>
                                            <TableCell>
                                                <Link href={wallet.href} className="font-medium text-primary hover:underline">
                                                    #{wallet.id}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{wallet.type}</TableCell>
                                            <TableCell><StatusBadge status={wallet.status} /></TableCell>
                                            <TableCell>{formatMoney(wallet.available_balance, wallet.currency, { currencyDisplay: 'code' })}</TableCell>
                                            <TableCell>{formatMoney(wallet.held_balance, wallet.currency, { currencyDisplay: 'code' })}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Payment methods</CardTitle>
                        <CardDescription>Stored methods and defaults</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!payment_methods?.length ? (
                            <p className="text-sm text-muted-foreground">No payment methods on file.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Kind</TableHead>
                                        <TableHead>Label</TableHead>
                                        <TableHead>Subtitle</TableHead>
                                        <TableHead>Default</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payment_methods.map((method) => (
                                        <TableRow key={method.id}>
                                            <TableCell>{method.kind}</TableCell>
                                            <TableCell className="font-medium">{method.label}</TableCell>
                                            <TableCell className="text-muted-foreground">{method.subtitle ?? '—'}</TableCell>
                                            <TableCell>{method.is_default ? 'Yes' : 'No'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent reviews</CardTitle>
                        <CardDescription>Reviews authored by this buyer</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!reviews?.length ? (
                            <p className="text-sm text-muted-foreground">No reviews found.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Seller</TableHead>
                                        <TableHead>Rating</TableHead>
                                        <TableHead>Comment</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reviews.map((review) => (
                                        <TableRow key={review.id}>
                                            <TableCell>{review.product}</TableCell>
                                            <TableCell>{review.seller}</TableCell>
                                            <TableCell>{review.rating}</TableCell>
                                            <TableCell className="max-w-[280px] truncate text-muted-foreground">{review.comment || '—'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6 border-amber-200/70 bg-amber-50/40">
                <CardHeader>
                    <CardTitle>Fraud controls</CardTitle>
                </CardHeader>
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
                                {(reason_codes || []).map((code) => <option key={code} value={code}>{code}</option>)}
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
                    {!pending_approvals?.length ? <p className="text-sm text-muted-foreground">No pending approvals.</p> : pending_approvals.map((approval) => (
                        <Form key={approval.id} action={approval.decision_url} method="post" className="mb-3 rounded-md border p-3">
                            <input type="hidden" name="decision" value="approve" />
                            <p className="text-sm font-medium">{approval.action_code}</p>
                            <p className="text-xs text-muted-foreground">{approval.reason_code} · requested by {approval.requested_by} · {fmtDate(approval.requested_at)}</p>
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
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Placed</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recent_orders.map((order) => (
                                    <TableRow key={order.id}>
                                        <TableCell>{order.order_number}</TableCell>
                                        <TableCell><StatusBadge status={order.status} /></TableCell>
                                        <TableCell>{order.total}</TableCell>
                                        <TableCell className="text-muted-foreground">{fmtDate(order.placed_at)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader><CardTitle>Change timeline</CardTitle></CardHeader>
                <CardContent>
                    {!timeline?.length ? <p className="text-sm text-muted-foreground">No timeline entries.</p> : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Actor</TableHead>
                                    <TableHead>Reason</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {timeline.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="text-muted-foreground">{fmtDate(entry.created_at)}</TableCell>
                                        <TableCell className="font-mono text-xs">{entry.action}</TableCell>
                                        <TableCell>{entry.actor}</TableCell>
                                        <TableCell>{entry.reason_code ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}

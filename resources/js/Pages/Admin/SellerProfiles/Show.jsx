import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

export default function SellerProfileShow({
    header,
    seller,
    stats,
    wallets,
    payment_methods,
    recent_products,
    recent_withdrawals,
    recent_reviews,
    list_href,
    state_update_url,
    reason_codes,
    pending_approvals,
    timeline,
}) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Seller Profiles</Link>
                </Button>
            </div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.reason ? <p className="mb-4 text-sm text-destructive">{errors.reason}</p> : null}

            <div className="grid gap-4 sm:grid-cols-4">
                <Card><CardHeader><CardTitle className="text-sm">Products</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.products_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Withdrawals</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.pending_withdrawals}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Reviews</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.reviews_total}</CardContent></Card>
                <Card><CardHeader><CardTitle className="text-sm">Open disputes</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{stats.open_disputes}</CardContent></Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Seller profile</CardTitle>
                        <CardDescription>Store identity and compliance state</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Display name: {seller.display_name ?? '—'}</p>
                        <p>Legal name: {seller.legal_name ?? '—'}</p>
                        <p>Country: {seller.country_code ?? '—'}</p>
                        <p>Default currency: {seller.default_currency ?? '—'}</p>
                        <p>Verification: <StatusBadge status={seller.verification_status} /></p>
                        <p>Risk score: {seller.risk_score ?? '—'} / 100</p>
                        <p>Risk band: {seller.risk_band ?? '—'}</p>
                        <p>Store status: <StatusBadge status={seller.store_status} /></p>
                        <p>Created: {fmtDate(seller.created_at)}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Linked account</CardTitle>
                        <CardDescription>Credentials and entry point into the seller console</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {!seller.account ? (
                            <p className="text-muted-foreground">No linked account</p>
                        ) : (
                            <>
                                <p>Email: {seller.account.email ?? '—'}</p>
                                <p>Phone: {seller.account.phone ?? '—'}</p>
                                <p>Status: <StatusBadge status={seller.account.status} /></p>
                                <p>Risk level: {seller.account.risk_level}</p>
                                <p>
                                    User profile:{' '}
                                    <Link href={seller.account.href} className="font-medium text-primary hover:underline">
                                        Open user details
                                    </Link>
                                </p>
                            </>
                        )}
                        {seller.storefront ? (
                            <div className="mt-3 rounded-md border p-3">
                                <p className="text-xs text-muted-foreground">Storefront</p>
                                <p className="font-medium">{seller.storefront.title ?? '—'}</p>
                                <p className="text-sm text-muted-foreground">Visibility: {seller.storefront.is_public ? 'Public' : 'Private'}</p>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Wallets</CardTitle>
                        <CardDescription>Available and held balance by wallet</CardDescription>
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
                                            <TableCell>{wallet.currency} {wallet.available_balance}</TableCell>
                                            <TableCell>{wallet.currency} {wallet.held_balance}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payment methods</CardTitle>
                        <CardDescription>Stored payout and checkout methods</CardDescription>
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
            </div>

            <Card className="mt-6 border-amber-200/70 bg-amber-50/40">
                <CardHeader>
                    <CardTitle>Store state controls</CardTitle>
                </CardHeader>
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
                                {(reason_codes || []).map((code) => <option key={code} value={code}>{code}</option>)}
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

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Recent products</CardTitle>
                        <CardDescription>Latest catalog items owned by this seller</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!recent_products?.length ? <p className="text-sm text-muted-foreground">No products yet.</p> : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Price</TableHead>
                                        <TableHead>Updated</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent_products.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>
                                                <Link href={product.href} className="font-medium text-primary hover:underline">{product.title}</Link>
                                            </TableCell>
                                            <TableCell><StatusBadge status={product.status} /></TableCell>
                                            <TableCell>{product.price}</TableCell>
                                            <TableCell className="text-muted-foreground">{fmtDate(product.updated_at)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent withdrawals</CardTitle>
                        <CardDescription>Payout requests and status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!recent_withdrawals?.length ? <p className="text-sm text-muted-foreground">No withdrawals yet.</p> : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Request</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Net payout</TableHead>
                                        <TableHead>Requested</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent_withdrawals.map((withdrawal) => (
                                        <TableRow key={withdrawal.id}>
                                            <TableCell>
                                                <Link href={withdrawal.href} className="font-medium text-primary hover:underline">#{withdrawal.id}</Link>
                                            </TableCell>
                                            <TableCell><StatusBadge status={withdrawal.status} /></TableCell>
                                            <TableCell>{withdrawal.currency} {withdrawal.net_payout_amount}</TableCell>
                                            <TableCell className="text-muted-foreground">{fmtDate(withdrawal.created_at)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Recent reviews</CardTitle>
                    <CardDescription>Feedback written against this seller</CardDescription>
                </CardHeader>
                <CardContent>
                    {!recent_reviews?.length ? <p className="text-sm text-muted-foreground">No reviews yet.</p> : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Buyer</TableHead>
                                    <TableHead>Rating</TableHead>
                                    <TableHead>Comment</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recent_reviews.map((review) => (
                                    <TableRow key={review.id}>
                                        <TableCell>{review.product}</TableCell>
                                        <TableCell>{review.buyer}</TableCell>
                                        <TableCell>{review.rating}</TableCell>
                                        <TableCell className="max-w-[360px] truncate text-muted-foreground">{review.comment || '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Pending approvals</CardTitle>
                </CardHeader>
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

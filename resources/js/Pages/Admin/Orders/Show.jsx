import { Head, Link } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

function fmtDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return String(value);
    }
}

function moneyLine(currency, amount) {
    const normalized = Number.parseFloat(String(amount ?? 0));
    const formatted = Number.isFinite(normalized) ? normalized.toFixed(2) : String(amount ?? '0.00');
    const code = (currency || '').toString().trim().toUpperCase();
    return code ? `${code} ${formatted}` : formatted;
}

function TimerPanel({ state }) {
    if (!state) {
        return <p className="text-sm text-muted-foreground">No timeout snapshot recorded.</p>;
    }

    const seconds = state.seconds_remaining;
    const hours = Number.isFinite(seconds) ? Math.floor(seconds / 3600) : null;
    const minutes = Number.isFinite(seconds) ? Math.floor((seconds % 3600) / 60) : null;

    return (
        <div className="space-y-4 text-sm">
            <div className="grid gap-2 sm:grid-cols-3">
                <p>
                    <span className="text-muted-foreground">Active timer</span>
                    <br />
                    <span className="font-medium">{state.active_timer ?? '—'}</span>
                </p>
                <p>
                    <span className="text-muted-foreground">Next action</span>
                    <br />
                    <span className="font-medium">{state.expiry_action ?? '—'}</span>
                </p>
                <p>
                    <span className="text-muted-foreground">Countdown</span>
                    <br />
                    <span className="font-medium">{hours === null ? '—' : `${hours}h ${minutes}m`}</span>
                </p>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
                {[
                    ['Unpaid warning', state.unpaid_reminder_at],
                    ['Unpaid expiry', state.expires_at],
                    ['Seller warning', state.seller_reminder_at],
                    ['Seller deadline', state.seller_deadline_at],
                    ['Review reminder 1', state.reminder_1_at],
                    ['Review reminder 2', state.reminder_2_at],
                    ['Escalation warning', state.escalation_warning_at],
                    ['Review expiry', state.buyer_review_expires_at],
                    ['Escalation', state.escalation_at],
                    ['Auto release', state.auto_release_at],
                ].map(([label, value]) => (
                    <p key={label}>
                        <span className="text-muted-foreground">{label}:</span> {fmtDate(value)}
                    </p>
                ))}
            </div>
            {!state.events?.length ? (
                <p className="text-muted-foreground">No timeout events processed yet.</p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Event</TableHead>
                            <TableHead>Action</TableHead>
                            <TableHead>Processed</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {state.events.map((event) => (
                            <TableRow key={`${event.event_type}-${event.processed_at}`}>
                                <TableCell>{event.event_type}</TableCell>
                                <TableCell>{event.action_taken}</TableCell>
                                <TableCell className="text-muted-foreground">{fmtDate(event.processed_at)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </div>
    );
}

function WalletTable({ wallets }) {
    if (!wallets?.length) {
        return <p className="text-sm text-muted-foreground">No wallets linked.</p>;
    }

    return (
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
                        <TableCell>
                            <StatusBadge status={wallet.status} />
                        </TableCell>
                        <TableCell>{wallet.currency} {wallet.available_balance}</TableCell>
                        <TableCell>{wallet.currency} {wallet.held_balance}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

export default function OrderShow({
    header,
    order,
    buyer,
    seller,
    items,
    escrow,
    seller_products,
    seller_reviews,
    seller_withdrawals,
    transitions,
    list_href,
}) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-6">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Back to orders</Link>
                </Button>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Order overview</CardTitle>
                        <CardDescription>Financial state and checkout summary</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2 text-sm">
                            <p>
                                <span className="text-muted-foreground">Status:</span> <StatusBadge status={order.status} />
                            </p>
                            <p>
                                <span className="text-muted-foreground">Order number:</span> {order.order_number}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Buyer:</span>{' '}
                                {buyer ? (
                                    <Link href={buyer.href} className="font-medium text-primary hover:underline">
                                        {buyer.email ?? `#${buyer.id}`}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Seller:</span>{' '}
                                {seller ? (
                                    <Link href={seller.href} className="font-medium text-primary hover:underline">
                                        {seller.display_name ?? `#${seller.id}`}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Placed:</span> {fmtDate(order.placed_at)}
                            </p>
                        </div>
                        <div className="space-y-2 text-sm">
                            <p>
                                <span className="text-muted-foreground">Payment method:</span> {order.payment_method ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Payment provider:</span> {order.payment_provider ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Promo code:</span> {order.promo_code ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Shipping method:</span> {order.shipping_method ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Payment intent:</span>{' '}
                                {order.payment_intent ? `${order.payment_intent.provider ?? '—'} · ${order.payment_intent.status}` : '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">Payment transaction:</span>{' '}
                                {order.payment_transaction ? `${order.payment_transaction.type} · ${order.payment_transaction.status}` : '—'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payment totals</CardTitle>
                        <CardDescription>Amounts as recorded by the backend</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">Gross</span>
                            <span className="font-medium">{moneyLine(order.currency, order.gross_amount)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">Discount</span>
                            <span className="font-medium text-emerald-700">-{moneyLine(order.currency, order.discount_amount)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">Fee</span>
                            <span className="font-medium">{moneyLine(order.currency, order.fee_amount)}</span>
                        </div>
                        <div className="flex items-center justify-between border-t pt-3">
                            <span className="text-muted-foreground">Net</span>
                            <span className="text-xl font-semibold text-primary">{moneyLine(order.currency, order.net_amount)}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Buyer profile</CardTitle>
                        <CardDescription>Account, wallet, and purchasing footprint</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        {!buyer ? (
                            <p className="text-muted-foreground">No buyer linked.</p>
                        ) : (
                            <>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <p>Email: {buyer.email ?? '—'}</p>
                                    <p>Status: <StatusBadge status={buyer.status} /></p>
                                </div>
                                <WalletTable wallets={buyer.wallets} />
                            </>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Seller profile</CardTitle>
                        <CardDescription>Store owner, payouts, and operational context</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        {!seller ? (
                            <p className="text-muted-foreground">No seller linked.</p>
                        ) : (
                            <>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <p>Display name: {seller.display_name ?? '—'}</p>
                                    <p>Legal name: {seller.legal_name ?? '—'}</p>
                                    <p>Account email: {seller.account_email ?? '—'}</p>
                                    <p>Verification: <StatusBadge status={seller.verification_status} /></p>
                                    <p>Store status: <StatusBadge status={seller.store_status} /></p>
                                </div>
                                <WalletTable wallets={seller.wallets} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Line items</CardTitle>
                    <CardDescription>Products captured on the order</CardDescription>
                </CardHeader>
                <CardContent>
                    {!items?.length ? (
                        <p className="text-sm text-muted-foreground">No line items.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Seller</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Delivery</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">{item.title}</TableCell>
                                        <TableCell>
                                            {item.product ? (
                                                <Link href={item.product.href} className="text-primary hover:underline">
                                                    {item.product.title}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>{item.seller}</TableCell>
                                        <TableCell>{item.line_total}</TableCell>
                                        <TableCell>{item.delivery}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Escrow</CardTitle>
                        <CardDescription>Money movement and hold state</CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {!escrow ? (
                            <p className="text-muted-foreground">No escrow account.</p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                <p>State: {escrow.state}</p>
                                <p>Currency: {escrow.currency}</p>
                                <p>Held: {escrow.currency} {escrow.held_amount}</p>
                                <p>Released: {escrow.currency} {escrow.released_amount}</p>
                                <p>Refunded: {escrow.currency} {escrow.refunded_amount}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Timeout automation</CardTitle>
                        <CardDescription>Frozen timers, next action, and processed scheduler events</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TimerPanel state={order.timeout_state} />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Seller financial history</CardTitle>
                        <CardDescription>Recent withdrawals and review signal</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div>
                            <p className="mb-2 font-medium">Withdrawals</p>
                            {!seller_withdrawals?.length ? (
                                <p className="text-muted-foreground">No withdrawals.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>ID</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Amount</TableHead>
                                            <TableHead>At</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {seller_withdrawals.map((w) => (
                                            <TableRow key={w.id}>
                                                <TableCell>
                                                    <Link href={w.href} className="text-primary hover:underline">#{w.id}</Link>
                                                </TableCell>
                                                <TableCell><StatusBadge status={w.status} /></TableCell>
                                                <TableCell>{w.currency} {w.net_payout_amount}</TableCell>
                                                <TableCell className="text-muted-foreground">{fmtDate(w.created_at)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Seller products</CardTitle>
                        <CardDescription>Latest catalog items from the seller</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!seller_products?.length ? (
                            <p className="text-sm text-muted-foreground">No products yet.</p>
                        ) : (
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
                                    {seller_products.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>
                                                <Link href={product.href} className="font-medium text-primary hover:underline">
                                                    {product.title}
                                                </Link>
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
                        <CardTitle>Seller reviews</CardTitle>
                        <CardDescription>Customer feedback written against seller orders</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!seller_reviews?.length ? (
                            <p className="text-sm text-muted-foreground">No reviews yet.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Rating</TableHead>
                                        <TableHead>Comment</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {seller_reviews.map((review) => (
                                        <TableRow key={review.id}>
                                            <TableCell>{review.product}</TableCell>
                                            <TableCell>{review.rating}</TableCell>
                                            <TableCell className="max-w-[320px] truncate text-muted-foreground">{review.comment || '—'}</TableCell>
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
                    <CardTitle>Recent state transitions</CardTitle>
                    <CardDescription>Latest fulfillment and payment transitions</CardDescription>
                </CardHeader>
                <CardContent>
                    {!transitions?.length ? (
                        <p className="text-sm text-muted-foreground">No transitions recorded.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>From</TableHead>
                                    <TableHead>To</TableHead>
                                    <TableHead>At</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transitions.map((transition, index) => (
                                    <TableRow key={index}>
                                        <TableCell>{transition.from || '—'}</TableCell>
                                        <TableCell>{transition.to || '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">{fmtDate(transition.at)}</TableCell>
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

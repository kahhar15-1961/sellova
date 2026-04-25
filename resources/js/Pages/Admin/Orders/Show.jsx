import { Head, Link } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function OrderShow({ header, order, items, escrow, transitions, list_href }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-6">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Back to orders</Link>
                </Button>
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                        <CardDescription>Order totals and buyer</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>
                            <span className="text-muted-foreground">Status:</span>{' '}
                            <StatusBadge status={order.status} />
                        </p>
                        <p>
                            <span className="text-muted-foreground">Buyer:</span> {order.buyer_email ?? '—'}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Gross:</span> {order.currency} {order.gross_amount}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Placed:</span> {order.placed_at ?? '—'}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Escrow</CardTitle>
                        <CardDescription>Funds held for this order</CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {!escrow ? (
                            <p className="text-muted-foreground">No escrow account.</p>
                        ) : (
                            <ul className="space-y-1">
                                <li>State: {escrow.state}</li>
                                <li>
                                    Held: {escrow.currency} {escrow.held_amount}
                                </li>
                                <li>
                                    Released: {escrow.currency} {escrow.released_amount}
                                </li>
                                <li>
                                    Refunded: {escrow.currency} {escrow.refunded_amount}
                                </li>
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Line items</CardTitle>
                </CardHeader>
                <CardContent>
                    {!items?.length ? (
                        <p className="text-sm text-muted-foreground">No line items.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Seller</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Delivery</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((it) => (
                                    <TableRow key={it.id}>
                                        <TableCell>{it.title}</TableCell>
                                        <TableCell>{it.seller}</TableCell>
                                        <TableCell>{it.line_total}</TableCell>
                                        <TableCell>{it.delivery}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Recent state transitions</CardTitle>
                    <CardDescription>Latest 50 transitions</CardDescription>
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
                                {transitions.map((t, i) => (
                                    <TableRow key={i}>
                                        <TableCell>{t.from || '—'}</TableCell>
                                        <TableCell>{t.to || '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">{t.at}</TableCell>
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

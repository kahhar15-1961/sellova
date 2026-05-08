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

export default function UserShow({ header, user, wallets, payment_methods, recent_reviews, recent_orders, can_manage, list_href, update_url }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Users</Link>
                </Button>
            </div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.state ? <p className="mb-4 text-sm text-destructive">{errors.state}</p> : null}

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Identity</CardTitle>
                        <CardDescription>Core account and access summary</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Email: {user.email ?? '—'}</p>
                        <p>Phone: {user.phone ?? '—'}</p>
                        <p>Status: <StatusBadge status={user.status} /></p>
                        <p>Risk: {user.risk_level}</p>
                        <p>Last login: {fmtDate(user.last_login_at)}</p>
                        <p>Created: {fmtDate(user.created_at)}</p>
                        <p>Roles: {user.roles?.map((role) => role.code).join(', ') || '—'}</p>
                        {user.seller_profile ? (
                            <p>
                                Seller profile:{' '}
                                <Link href={user.seller_profile.href} className="font-medium text-primary hover:underline">
                                    {user.seller_profile.display_name ?? 'Open seller profile'}
                                </Link>{' '}
                                ({user.seller_profile.verification_status})
                            </p>
                        ) : (
                            <p>Seller profile: none</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Wallets and payments</CardTitle>
                        <CardDescription>Wallet balances and saved payment methods</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div>
                            <p className="mb-2 text-sm font-medium">Wallets</p>
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
                        </div>

                        <div>
                            <p className="mb-2 text-sm font-medium">Payment methods</p>
                            {!payment_methods?.length ? (
                                <p className="text-sm text-muted-foreground">No payment methods on file.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Kind</TableHead>
                                            <TableHead>Label</TableHead>
                                            <TableHead>Default</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payment_methods.map((method) => (
                                            <TableRow key={method.id}>
                                                <TableCell>{method.kind}</TableCell>
                                                <TableCell className="font-medium">{method.label}</TableCell>
                                                <TableCell>{method.is_default ? 'Yes' : 'No'}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {can_manage ? (
                <Card className="mt-6 border-amber-200/70 bg-amber-50/40">
                    <CardHeader>
                        <CardTitle>Admin controls</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form action={update_url} method="post" className="grid gap-3 md:grid-cols-4">
                            <div>
                                <label htmlFor="status" className="mb-1 block text-sm font-medium">Status</label>
                                <select id="status" name="status" defaultValue={user.status} className="h-10 w-full rounded-md border px-3 text-sm">
                                    <option value="active">active</option>
                                    <option value="suspended">suspended</option>
                                    <option value="closed">closed</option>
                                </select>
                            </div>
                            <div>
                                <label htmlFor="risk_level" className="mb-1 block text-sm font-medium">Risk level</label>
                                <select id="risk_level" name="risk_level" defaultValue={user.risk_level} className="h-10 w-full rounded-md border px-3 text-sm">
                                    <option value="low">low</option>
                                    <option value="medium">medium</option>
                                    <option value="high">high</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <label htmlFor="reason" className="mb-1 block text-sm font-medium">Reason (audit)</label>
                                <input id="reason" name="reason" className="h-10 w-full rounded-md border px-3 text-sm" placeholder="Optional reason code/context" />
                            </div>
                            <div className="md:col-span-4">
                                <Button type="submit">Save user state</Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Recent reviews</CardTitle>
                        <CardDescription>Feedback written by this user</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!recent_reviews?.length ? (
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
                                    {recent_reviews.map((review) => (
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

                <Card>
                    <CardHeader>
                        <CardTitle>Recent orders</CardTitle>
                        <CardDescription>Latest purchasing history</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!recent_orders?.length ? (
                            <p className="text-sm text-muted-foreground">No recent orders for this user.</p>
                        ) : (
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
            </div>
        </AdminLayout>
    );
}

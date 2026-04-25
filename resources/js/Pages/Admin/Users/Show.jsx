import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function UserShow({ header, user, recent_orders, can_manage, list_href, update_url }) {
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

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Identity</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Email: {user.email ?? '—'}</p>
                        <p>Phone: {user.phone ?? '—'}</p>
                        <p>
                            Status: <StatusBadge status={user.status} />
                        </p>
                        <p>Risk: {user.risk_level}</p>
                        <p>Last login: {user.last_login_at ?? '—'}</p>
                        <p>Created: {user.created_at}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Access</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Roles: {user.roles?.map((r) => r.code).join(', ') || '—'}</p>
                        {user.seller_profile ? (
                            <p>
                                Seller profile: {user.seller_profile.display_name} ({user.seller_profile.verification_status})
                            </p>
                        ) : (
                            <p>Seller profile: none</p>
                        )}
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
                                <label htmlFor="status" className="mb-1 block text-sm font-medium">
                                    Status
                                </label>
                                <select id="status" name="status" defaultValue={user.status} className="h-10 w-full rounded-md border px-3 text-sm">
                                    <option value="active">active</option>
                                    <option value="suspended">suspended</option>
                                    <option value="closed">closed</option>
                                </select>
                            </div>
                            <div>
                                <label htmlFor="risk_level" className="mb-1 block text-sm font-medium">
                                    Risk level
                                </label>
                                <select id="risk_level" name="risk_level" defaultValue={user.risk_level} className="h-10 w-full rounded-md border px-3 text-sm">
                                    <option value="low">low</option>
                                    <option value="medium">medium</option>
                                    <option value="high">high</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <label htmlFor="reason" className="mb-1 block text-sm font-medium">
                                    Reason (audit)
                                </label>
                                <input id="reason" name="reason" className="h-10 w-full rounded-md border px-3 text-sm" placeholder="Optional reason code/context" />
                            </div>
                            <div className="md:col-span-4">
                                <Button type="submit">Save user state</Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Recent orders</CardTitle>
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
                                {recent_orders.map((o) => (
                                    <TableRow key={o.id}>
                                        <TableCell>{o.order_number}</TableCell>
                                        <TableCell><StatusBadge status={o.status} /></TableCell>
                                        <TableCell>{o.total}</TableCell>
                                        <TableCell className="text-muted-foreground">{o.placed_at ?? '—'}</TableCell>
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

import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function ProductShow({ header, product, can_moderate, moderate_url, list_href }) {
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
                                <label htmlFor="reason" className="mb-1 block text-sm font-medium">
                                    Reason (audit)
                                </label>
                                <input id="reason" name="reason" className="h-10 w-full rounded-md border px-3 text-sm" placeholder="Optional moderation reason" />
                            </div>
                            <div className="md:col-span-3">
                                <Button type="submit">Apply moderation update</Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}
        </AdminLayout>
    );
}

import { Form, Head, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function ShippingMethodsIndex({
    header,
    rows = [],
    store_url,
    update_url_template,
    toggle_url_template,
    processing_options = [],
}) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Create shipping method</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form action={store_url} method="post" className="space-y-3">
                            <Input name="name" placeholder="Inside Dhaka, Outside Dhaka..." required />
                            <Input name="code" placeholder="Code (optional)" />
                            <Input name="suggested_fee" type="number" step="0.01" min="0" placeholder="Suggested fee" required />
                            <select name="processing_time_label" defaultValue="1-2 Business Days" className="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                {processing_options.map((item) => <option key={item} value={item}>{item}</option>)}
                            </select>
                            <Input name="sort_order" type="number" defaultValue="0" placeholder="Sort order" />
                            <input type="hidden" name="is_active" value="0" />
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_active" value="1" defaultChecked />
                                Active
                            </label>
                            <Button type="submit" className="w-full">Create method</Button>
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Seller-selectable shipping methods</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {rows.length === 0 ? (
                            <div className="rounded-md border border-dashed p-8 text-sm text-muted-foreground">No shipping methods yet.</div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3">Method</th>
                                            <th className="px-4 py-3">Suggested fee</th>
                                            <th className="px-4 py-3">Processing</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Sort</th>
                                            <th className="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => {
                                            const active = Boolean(row.is_active);
                                            return (
                                                <tr key={row.id} className="border-t align-top">
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold">{row.name}</div>
                                                        <div className="text-xs text-muted-foreground">{row.code}</div>
                                                    </td>
                                                    <td className="px-4 py-3">৳ {row.suggested_fee}</td>
                                                    <td className="px-4 py-3">{row.processing_time_label}</td>
                                                    <td className="px-4 py-3"><StatusBadge status={active ? 'active' : 'inactive'} /></td>
                                                    <td className="px-4 py-3">{row.sort_order}</td>
                                                    <td className="px-4 py-3">
                                                        <details>
                                                            <summary className="cursor-pointer text-primary">Edit</summary>
                                                            <Form action={update_url_template.replace('__ID__', row.id)} method="patch" className="mt-3 grid min-w-72 gap-2 rounded-md border bg-muted/20 p-3">
                                                                <Input name="name" defaultValue={row.name} />
                                                                <Input name="code" defaultValue={row.code} />
                                                                <Input name="suggested_fee" type="number" step="0.01" min="0" defaultValue={row.suggested_fee} />
                                                                <select name="processing_time_label" defaultValue={row.processing_time_label} className="h-9 rounded-md border bg-background px-3 text-sm">
                                                                    {processing_options.map((item) => <option key={item} value={item}>{item}</option>)}
                                                                </select>
                                                                <Input name="sort_order" type="number" defaultValue={row.sort_order} />
                                                                <input type="hidden" name="is_active" value="0" />
                                                                <label className="flex items-center gap-2 text-sm">
                                                                    <input type="checkbox" name="is_active" value="1" defaultChecked={active} />
                                                                    Active
                                                                </label>
                                                                <Button type="submit" size="sm">Save</Button>
                                                            </Form>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                className="mt-2"
                                                                onClick={() => router.post(toggle_url_template.replace('__ID__', row.id), { is_active: !active }, { preserveScroll: true })}
                                                            >
                                                                {active ? 'Disable' : 'Enable'}
                                                            </Button>
                                                        </details>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

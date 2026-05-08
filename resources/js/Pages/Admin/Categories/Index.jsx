import { Form, Head, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

function parentOptions(categories, currentId = null) {
    return (categories || []).filter((row) => Number(row.id) !== Number(currentId));
}

export default function CategoriesIndex({
    header,
    categories = [],
    requests = [],
    store_url,
    update_url_template,
    toggle_url_template,
    approve_request_url_template,
    reject_request_url_template,
}) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Create category or subcategory</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form action={store_url} method="post" className="space-y-3">
                            <Input name="name" placeholder="Category name" required />
                            <Input name="slug" placeholder="Slug (optional)" />
                            <select name="parent_id" className="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                <option value="">Root category</option>
                                {categories.map((row) => (
                                    <option key={row.id} value={row.id}>{row.name}</option>
                                ))}
                            </select>
                            <Textarea name="description" placeholder="Description" />
                            <Input name="image_url" placeholder="Image URL or storage path" />
                            <Input name="sort_order" type="number" defaultValue="0" placeholder="Sort order" />
                            <input type="hidden" name="is_active" value="0" />
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_active" value="1" defaultChecked />
                                Active
                            </label>
                            <Button type="submit" className="w-full">Create category / subcategory</Button>
                        </Form>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Category catalog</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {categories.length === 0 ? (
                                <div className="rounded-md border border-dashed p-8 text-sm text-muted-foreground">No categories yet.</div>
                            ) : (
                                <div className="overflow-x-auto rounded-md border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3">Name</th>
                                                <th className="px-4 py-3">Level</th>
                                                <th className="px-4 py-3">Products</th>
                                                <th className="px-4 py-3">Status</th>
                                                <th className="px-4 py-3">Sort</th>
                                                <th className="px-4 py-3">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {categories.map((row) => {
                                                const active = Boolean(row.is_active);
                                                return (
                                                    <tr key={row.id} className="border-t align-top">
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold">{row.name}</div>
                                                            <div className="text-xs text-muted-foreground">{row.slug}</div>
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">{row.parent ? `Subcategory of ${row.parent}` : 'Root category'}</td>
                                                        <td className="px-4 py-3">{row.products_count ?? 0}</td>
                                                        <td className="px-4 py-3"><StatusBadge status={active ? 'active' : 'inactive'} /></td>
                                                        <td className="px-4 py-3">{row.sort_order ?? 0}</td>
                                                        <td className="px-4 py-3">
                                                            <details className="space-y-3">
                                                                <summary className="cursor-pointer text-primary">Edit</summary>
                                                                <Form action={update_url_template.replace('__ID__', row.id)} method="patch" className="mt-3 grid gap-2 rounded-md border bg-muted/20 p-3">
                                                                    <Input name="name" defaultValue={row.name || ''} />
                                                                    <Input name="slug" defaultValue={row.slug || ''} />
                                                                    <select name="parent_id" defaultValue={row.parent_id || ''} className="h-9 rounded-md border bg-background px-3 text-sm">
                                                                        <option value="">Root category</option>
                                                                        {parentOptions(categories, row.id).map((option) => (
                                                                            <option key={option.id} value={option.id}>{option.name}</option>
                                                                        ))}
                                                                    </select>
                                                                    <Textarea name="description" defaultValue={row.description || ''} />
                                                                    <Input name="image_url" defaultValue={row.image_url || ''} />
                                                                    <Input name="sort_order" type="number" defaultValue={row.sort_order ?? 0} />
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

                    <Card>
                        <CardHeader>
                            <CardTitle>Seller category requests</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {requests.length === 0 ? (
                                <div className="rounded-md border border-dashed p-8 text-sm text-muted-foreground">No seller category requests.</div>
                            ) : (
                                <div className="overflow-x-auto rounded-md border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3">Request</th>
                                                <th className="px-4 py-3">Seller</th>
                                                <th className="px-4 py-3">Status</th>
                                                <th className="px-4 py-3">Created</th>
                                                <th className="px-4 py-3">Review</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {requests.map((row) => (
                                                <tr key={row.id} className="border-t align-top">
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold">{row.name}</div>
                                                        <div className="text-xs text-muted-foreground">Parent: {row.parent || 'Root'} · Example: {row.example_product_name || '—'}</div>
                                                        <div className="mt-1 text-xs text-muted-foreground">{row.reason || 'No reason provided.'}</div>
                                                    </td>
                                                    <td className="px-4 py-3">{row.seller}</td>
                                                    <td className="px-4 py-3"><StatusBadge status={row.status} /></td>
                                                    <td className="px-4 py-3 text-muted-foreground">{fmtDate(row.created_at)}</td>
                                                    <td className="px-4 py-3">
                                                        {row.status === 'pending' ? (
                                                            <div className="grid gap-2 min-w-60">
                                                                <select id={`parent-${row.id}`} className="h-9 rounded-md border bg-background px-3 text-sm" defaultValue={row.parent_id || ''}>
                                                                    <option value="">Root category</option>
                                                                    {categories.map((cat) => (
                                                                        <option key={cat.id} value={cat.id}>{cat.name}</option>
                                                                    ))}
                                                                </select>
                                                                <Input id={`note-${row.id}`} placeholder="Admin note" />
                                                                <div className="flex gap-2">
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        onClick={() => {
                                                                            const parent = document.getElementById(`parent-${row.id}`)?.value || '';
                                                                            const note = document.getElementById(`note-${row.id}`)?.value || '';
                                                                            router.post(approve_request_url_template.replace('__ID__', row.id), { parent_id: parent, admin_note: note }, { preserveScroll: true });
                                                                        }}
                                                                    >
                                                                        Approve
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="destructive"
                                                                        onClick={() => {
                                                                            const note = document.getElementById(`note-${row.id}`)?.value || '';
                                                                            router.post(reject_request_url_template.replace('__ID__', row.id), { admin_note: note }, { preserveScroll: true });
                                                                        }}
                                                                    >
                                                                        Reject
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <div className="text-xs text-muted-foreground">
                                                                {row.resolved_category ? `Created: ${row.resolved_category}` : row.admin_note || 'Reviewed'}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}

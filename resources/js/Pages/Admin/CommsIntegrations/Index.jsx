import { Form, Head, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/admin/StatusBadge';

function fmtDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); } catch { return String(iso); }
}

export default function CommsIntegrationsIndex({ header, rows, store_url, test_url }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <Card className="mb-6">
                <CardHeader><CardTitle>Add integration</CardTitle></CardHeader>
                <CardContent>
                    <Form action={store_url} method="post" className="grid gap-3 lg:grid-cols-2">
                        <input name="name" className="h-9 rounded-md border px-2 text-sm" placeholder="Name" />
                        <select name="channel" className="h-9 rounded-md border px-2 text-sm">
                            <option value="webhook">webhook</option>
                            <option value="email">email</option>
                        </select>
                        <input name="webhook_url" className="h-9 rounded-md border px-2 text-sm" placeholder="Webhook URL (for webhook channel)" />
                        <input name="email_to" className="h-9 rounded-md border px-2 text-sm" placeholder="Email target (for email channel)" />
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" defaultChecked /> enabled</label>
                        <div className="flex gap-2">
                            <Button type="submit">Add integration</Button>
                            <Button type="button" variant="outline" onClick={() => router.post(test_url, {}, { preserveScroll: true })}>Send test webhook</Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <DataTableShell
                columns={['name', 'channel', 'is_enabled', 'webhook_url', 'email_to', 'last_tested_at']}
                rows={rows}
                emptyTitle="No comms integrations"
                renderers={{
                    is_enabled: (v) => <StatusBadge status={v ? 'active' : 'inactive'} />,
                    last_tested_at: (v) => <span className="text-muted-foreground">{fmtDate(v)}</span>,
                }}
            />
        </AdminLayout>
    );
}

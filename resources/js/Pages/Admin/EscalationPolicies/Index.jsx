import { Form, Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { DataTableShell } from '@/components/admin/DataTableShell';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export default function EscalationPoliciesIndex({ header, policies, rotations, users, policy_store_url, rotation_store_url }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Upsert policy</CardTitle></CardHeader>
                    <CardContent>
                        <Form action={policy_store_url} method="post" className="space-y-3">
                            <select name="queue_code" className="h-9 w-full rounded-md border px-2 text-sm">
                                <option value="disputes">disputes</option>
                                <option value="withdrawals">withdrawals</option>
                                <option value="approvals">approvals</option>
                            </select>
                            <select name="default_severity" className="h-9 w-full rounded-md border px-2 text-sm">
                                <option value="medium">medium</option>
                                <option value="high">high</option>
                                <option value="critical">critical</option>
                            </select>
                            <input name="on_call_role_code" className="h-9 w-full rounded-md border px-2 text-sm" placeholder="on_call_role_code e.g. dispute_officer" />
                            <div className="grid grid-cols-2 gap-2">
                                <input name="ack_sla_minutes" defaultValue="30" className="h-9 rounded-md border px-2 text-sm" placeholder="ack SLA minutes" />
                                <input name="resolve_sla_minutes" defaultValue="240" className="h-9 rounded-md border px-2 text-sm" placeholder="resolve SLA minutes" />
                            </div>
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_assign_on_call" value="1" defaultChecked /> auto assign on-call</label>
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" defaultChecked /> enabled</label>
                            <Button type="submit">Save policy</Button>
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Add on-call rotation</CardTitle></CardHeader>
                    <CardContent>
                        <Form action={rotation_store_url} method="post" className="space-y-3">
                            <input name="role_code" className="h-9 w-full rounded-md border px-2 text-sm" placeholder="role_code" />
                            <select name="user_id" className="h-9 w-full rounded-md border px-2 text-sm">
                                {(users || []).map((u) => <option key={u.id} value={u.id}>{u.email}</option>)}
                            </select>
                            <div className="grid grid-cols-4 gap-2">
                                <input name="weekday" defaultValue="1" className="h-9 rounded-md border px-2 text-sm" placeholder="weekday" />
                                <input name="start_hour" defaultValue="0" className="h-9 rounded-md border px-2 text-sm" placeholder="start" />
                                <input name="end_hour" defaultValue="23" className="h-9 rounded-md border px-2 text-sm" placeholder="end" />
                                <input name="priority" defaultValue="100" className="h-9 rounded-md border px-2 text-sm" placeholder="priority" />
                            </div>
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" defaultChecked /> active</label>
                            <Button type="submit">Add rotation</Button>
                        </Form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 space-y-6">
                <DataTableShell columns={['queue_code', 'default_severity', 'on_call_role_code', 'ack_sla_minutes', 'resolve_sla_minutes', 'is_enabled']} rows={policies || []} emptyTitle="No policies" />
                <DataTableShell columns={['role_code', 'user_email', 'weekday', 'window', 'priority', 'is_active']} rows={rotations || []} emptyTitle="No rotations" />
            </div>
        </AdminLayout>
    );
}

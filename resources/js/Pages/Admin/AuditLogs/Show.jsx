import { Head, Link } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

function pretty(v) {
    try {
        return JSON.stringify(v ?? {}, null, 2);
    } catch {
        return String(v ?? '');
    }
}

export default function AuditLogShow({ header, record, list_href }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Audit logs</Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Record metadata</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-2 text-sm sm:grid-cols-2">
                    <p>ID: {record.id}</p>
                    <p>UUID: {record.uuid}</p>
                    <p>Time: {record.time}</p>
                    <p>Actor: {record.actor ?? '—'}</p>
                    <p>Action: {record.action}</p>
                    <p>Target: {record.target_type} #{record.target_id}</p>
                    <p>Reason code: {record.reason_code ?? '—'}</p>
                    <p>Correlation: {record.correlation_id ?? '—'}</p>
                    <p>IP: {record.ip_address ?? '—'}</p>
                    <p>User agent: {record.user_agent ?? '—'}</p>
                </CardContent>
            </Card>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Before</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-auto rounded-md bg-muted p-3 text-xs">{pretty(record.before_json)}</pre>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>After</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-auto rounded-md bg-muted p-3 text-xs">{pretty(record.after_json)}</pre>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

import { Form, Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export default function RunbooksIndex({ header, runbooks, runbook_store_url, step_store_url }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Create runbook</CardTitle></CardHeader>
                    <CardContent>
                        <Form action={runbook_store_url} method="post" className="space-y-3">
                            <select name="queue_code" className="h-9 w-full rounded-md border px-2 text-sm">
                                <option value="disputes">disputes</option>
                                <option value="withdrawals">withdrawals</option>
                                <option value="approvals">approvals</option>
                            </select>
                            <input name="title" className="h-9 w-full rounded-md border px-2 text-sm" placeholder="Runbook title" />
                            <textarea name="objective" className="min-h-20 w-full rounded-md border px-2 py-2 text-sm" placeholder="Objective" />
                            <input type="hidden" name="is_active" value="0" />
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" defaultChecked value="1" /> active</label>
                            <Button type="submit">Create runbook</Button>
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Add runbook step</CardTitle></CardHeader>
                    <CardContent>
                        <Form action={step_store_url} method="post" className="space-y-3">
                            <select name="runbook_id" className="h-9 w-full rounded-md border px-2 text-sm">
                                {(runbooks || []).map((r) => <option key={r.id} value={r.id}>{`${r.queue_code} · ${r.title}`}</option>)}
                            </select>
                            <div className="grid grid-cols-2 gap-2">
                                <input name="step_order" defaultValue="1" className="h-9 rounded-md border px-2 text-sm" placeholder="step order" />
                                <input type="hidden" name="is_required" value="0" />
                                <label className="flex items-center gap-2 text-sm rounded-md border px-2"><input type="checkbox" name="is_required" value="1" defaultChecked /> required</label>
                            </div>
                            <input type="hidden" name="evidence_required" value="0" />
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="evidence_required" value="1" /> evidence required</label>
                            <textarea name="instruction" className="min-h-20 w-full rounded-md border px-2 py-2 text-sm" placeholder="Step instruction" />
                            <Button type="submit">Add step</Button>
                        </Form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 space-y-4">
                {(runbooks || []).map((r) => (
                    <Card key={r.id}>
                        <CardHeader><CardTitle>{r.title} <span className="text-sm text-muted-foreground">({r.queue_code})</span></CardTitle></CardHeader>
                        <CardContent className="space-y-2">
                            <p className="text-sm text-muted-foreground">{r.objective || '—'}</p>
                            {(r.steps || []).length === 0 ? <p className="text-sm text-muted-foreground">No steps yet.</p> : (r.steps || []).map((s) => (
                                <div key={s.id} className="rounded-md border p-2 text-sm">
                                    <p><strong>#{s.step_order}</strong> {s.instruction}</p>
                                    <p className="text-xs text-muted-foreground">{s.is_required ? 'required' : 'optional'} · {s.evidence_required ? 'evidence required' : 'no evidence required'}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AdminLayout>
    );
}

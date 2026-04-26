import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

function fmtDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); } catch { return String(iso); }
}

export default function EscalationShow({
    header,
    incident,
    runbook,
    events,
    comms_logs: commsLogs,
    complete_step_url_template: completeStepUrlTemplate,
    retry_comms_url_template: retryCommsUrlTemplate,
}) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};
    const completeStepUrl = (id) => String(completeStepUrlTemplate || '').replace('__STEP__', String(id));
    const retryLogUrl = (id) => String(retryCommsUrlTemplate || '').replace('__LOG__', String(id));
    const [optimisticSteps, setOptimisticSteps] = useState(() => (runbook?.steps || []));
    const [optimisticComms, setOptimisticComms] = useState(() => (commsLogs || []));
    const [stepSubmitting, setStepSubmitting] = useState({});
    const [commsSubmitting, setCommsSubmitting] = useState({});
    const [evidenceByStep, setEvidenceByStep] = useState({});

    const mergedSteps = useMemo(() => optimisticSteps, [optimisticSteps]);
    const mergedCommsLogs = useMemo(() => optimisticComms, [optimisticComms]);
    const requiredCompleted = useMemo(
        () => mergedSteps.filter((s) => s.is_required && s.status === 'completed').length,
        [mergedSteps],
    );

    const completeStep = (step) => {
        const evidence = String(evidenceByStep[step.id] ?? '').trim();
        if (step.evidence_required && evidence === '') return;
        setStepSubmitting((prev) => ({ ...prev, [step.id]: true }));
        setOptimisticSteps((prev) => prev.map((s) => (
            s.id === step.id
                ? {
                    ...s,
                    status: 'completed',
                    evidence_notes: evidence || s.evidence_notes,
                    completed_by: 'You',
                    completed_at: new Date().toISOString(),
                }
                : s
        )));
        router.post(
            completeStepUrl(step.id),
            { evidence_notes: evidence },
            {
                preserveScroll: true,
                onError: () => {
                    setOptimisticSteps(runbook?.steps || []);
                },
                onFinish: () => {
                    setStepSubmitting((prev) => ({ ...prev, [step.id]: false }));
                },
            },
        );
    };

    const retryComms = (log) => {
        setCommsSubmitting((prev) => ({ ...prev, [log.id]: true }));
        setOptimisticComms((prev) => prev.map((l) => (
            l.id === log.id
                ? { ...l, status: 'retrying', attempt_count: Number(l.attempt_count || 0) + 1, next_retry_at: new Date().toISOString() }
                : l
        )));
        router.post(
            retryLogUrl(log.id),
            {},
            {
                preserveScroll: true,
                onError: () => {
                    setOptimisticComms(commsLogs || []);
                },
                onFinish: () => {
                    setCommsSubmitting((prev) => ({ ...prev, [log.id]: false }));
                },
            },
        );
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            {flash.error ? <p className="mb-4 rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">{flash.error}</p> : null}
            {flash.success ? <p className="mb-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400">{flash.success}</p> : null}
            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader><CardTitle>Incident summary</CardTitle></CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p><strong>Queue:</strong> {incident.queue_code}</p>
                        <p><strong>Target:</strong> {incident.target_type} #{incident.target_id}{incident.target_href ? <a href={incident.target_href} className="ml-2 text-primary hover:underline">Open</a> : null}</p>
                        <p><strong>Status:</strong> <StatusBadge status={incident.status} /></p>
                        <p><strong>Severity:</strong> <StatusBadge status={incident.severity} /></p>
                        <p><strong>Assignee:</strong> {incident.assigned_user}</p>
                        <p><strong>Opened:</strong> {fmtDate(incident.opened_at)}</p>
                        <p><strong>Ack due:</strong> {fmtDate(incident.ack_due_at)}</p>
                        <p><strong>Resolve due:</strong> {fmtDate(incident.resolve_due_at)}</p>
                        <p><strong>Ladder level:</strong> L{incident.current_ladder_level ?? 1}</p>
                        <p><strong>Next ladder:</strong> {fmtDate(incident.next_ladder_at)}</p>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader><CardTitle>Target snapshot</CardTitle></CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {!incident.target_snapshot ? (
                            <p className="text-muted-foreground">No target snapshot available.</p>
                        ) : (
                            <>
                                <p><strong>Type:</strong> {incident.target_snapshot.type}</p>
                                <p><strong>Status:</strong> {incident.target_snapshot.status ?? '—'}</p>
                                {incident.target_snapshot.order_number ? <p><strong>Order:</strong> {incident.target_snapshot.order_number}</p> : null}
                                {incident.target_snapshot.seller ? <p><strong>Seller:</strong> {incident.target_snapshot.seller}</p> : null}
                                {incident.target_snapshot.amount ? <p><strong>Amount:</strong> {incident.target_snapshot.amount}</p> : null}
                                <p><strong>Assignee:</strong> {incident.target_snapshot.assignee ?? 'Unassigned'}</p>
                                <p><strong>Escalated at:</strong> {fmtDate(incident.target_snapshot.escalated_at)}</p>
                                <p><strong>Escalation reason:</strong> {incident.target_snapshot.escalation_reason ?? '—'}</p>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-1">
                <Card className="lg:col-span-2">
                    <CardHeader><CardTitle>Runbook execution</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        {!runbook ? (
                            <p className="text-sm text-muted-foreground">No active runbook mapped for this queue.</p>
                        ) : (
                            <>
                                <div className="flex items-center justify-between text-sm">
                                    <p><strong>{runbook.title}</strong> <span className="text-muted-foreground">({runbook.status})</span></p>
                                    <p className="text-muted-foreground">Required completed: {requiredCompleted}/{runbook.required_total}</p>
                                </div>
                                <div className="space-y-2">
                                    {(mergedSteps || []).map((s) => (
                                        <div key={s.id} className="rounded-md border p-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="text-sm font-medium">#{s.step_order} {s.instruction}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {s.is_required ? 'required' : 'optional'} · {s.evidence_required ? 'evidence required' : 'no evidence required'}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">Status: {s.status} · Completed by: {s.completed_by} · {fmtDate(s.completed_at)}</p>
                                                </div>
                                                {s.status !== 'completed' ? (
                                                    <div className="flex items-center gap-2">
                                                        <input
                                                            name="evidence_notes"
                                                            className="h-8 rounded-md border px-2 text-xs"
                                                            placeholder={s.evidence_required ? 'Evidence notes (required)' : 'Evidence notes'}
                                                            value={evidenceByStep[s.id] ?? ''}
                                                            onChange={(e) => setEvidenceByStep((prev) => ({ ...prev, [s.id]: e.target.value }))}
                                                            disabled={stepSubmitting[s.id] === true}
                                                        />
                                                        <Button
                                                            size="sm"
                                                            type="button"
                                                            onClick={() => completeStep(s)}
                                                            disabled={(s.evidence_required && String(evidenceByStep[s.id] ?? '').trim() === '') || stepSubmitting[s.id] === true}
                                                        >
                                                            {stepSubmitting[s.id] ? 'Completing…' : 'Complete'}
                                                        </Button>
                                                    </div>
                                                ) : <StatusBadge status="completed" />}
                                            </div>
                                            {errors.evidence_notes ? <p className="mt-1 text-xs text-destructive">{errors.evidence_notes}</p> : null}
                                            {s.evidence_notes ? <p className="mt-2 text-xs text-muted-foreground">Evidence: {s.evidence_notes}</p> : null}
                                        </div>
                                    ))}
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Incident timeline</CardTitle></CardHeader>
                    <CardContent className="space-y-2">
                        {(events || []).length === 0 ? (
                            <p className="text-sm text-muted-foreground">No timeline events.</p>
                        ) : (
                            (events || []).map((e) => (
                                <div key={e.id} className="rounded-md border p-2 text-sm">
                                    <p><strong>{e.event_type}</strong> · {e.actor} · {fmtDate(e.created_at)}</p>
                                    <pre className="mt-1 overflow-auto rounded bg-muted p-2 text-xs">{JSON.stringify(e.payload_json, null, 2)}</pre>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Comms delivery logs</CardTitle></CardHeader>
                    <CardContent className="space-y-2">
                        {(commsLogs || []).length === 0 ? (
                            <p className="text-sm text-muted-foreground">No comms logs yet.</p>
                        ) : (
                            (mergedCommsLogs || []).map((l) => (
                                <div key={l.id} className="rounded-md border p-2 text-sm">
                                    <div className="flex items-center justify-between gap-2">
                                        <p>
                                            <strong>{l.event_type}</strong> · {l.integration} ({l.channel}) ·
                                            {' '}<StatusBadge status={l.status} className="ml-1" />
                                        </p>
                                        {(l.status === 'failed' || l.status === 'retrying') ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => retryComms(l)}
                                                disabled={commsSubmitting[l.id] === true}
                                            >
                                                {commsSubmitting[l.id] ? 'Retrying…' : 'Retry now'}
                                            </Button>
                                        ) : null}
                                    </div>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Attempts: {l.attempt_count} · Next retry: {fmtDate(l.next_retry_at)} · Delivered: {fmtDate(l.delivered_at)}
                                    </p>
                                    {l.last_error ? <p className="mt-1 text-xs text-destructive">{l.last_error}</p> : null}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

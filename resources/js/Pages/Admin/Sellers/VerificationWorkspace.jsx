import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useAdminCan } from '@/hooks/useAdminCan';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return '—';
    }
}

/**
 * @param {{
 *   header: { title: string, description?: string, breadcrumbs?: { label: string, href?: string }[] },
 *   workspace: object,
 * }} props
 */
export default function VerificationWorkspace({ header, workspace }) {
    const flash = usePage().props.flash ?? {};
    const errors = usePage().props.errors ?? {};
    const can = useAdminCan();

    const [approveOpen, setApproveOpen] = useState(false);

    const reviewForm = useForm({
        decision: 'approved',
        reason: '',
    });
    const noteForm = useForm({
        note: '',
    });

    const flags = workspace?.flags ?? {};
    const kyc = workspace?.kyc ?? {};
    const seller = workspace?.seller ?? null;
    const account = workspace?.account ?? null;
    const documents = workspace?.documents ?? [];
    const notes = workspace?.notes ?? [];
    const history = workspace?.history ?? [];
    const reviewers = workspace?.reviewers ?? [];
    const documentInsights = workspace?.document_insights ?? {};
    const routes = workspace?.routes ?? {};
    const [reassignTo, setReassignTo] = useState(() => String(kyc.assigned_to_user_id ?? reviewers[0]?.value ?? ''));

    const postClaim = () => {
        router.post(routes.claim ?? '', {}, { preserveScroll: true });
    };

    const postReassign = () => {
        if (!reassignTo) return;
        router.post(
            routes.reassign ?? '',
            { assignee_user_id: Number(reassignTo) },
            { preserveScroll: true },
        );
    };

    const postReview = (decision) => {
        reviewForm.setData({
            decision,
            reason: decision === 'approved' ? '' : reviewForm.data.reason,
        });
        reviewForm.post(routes.review ?? '', {
            preserveScroll: true,
            onSuccess: () => {
                if (decision === 'approved') {
                    setApproveOpen(false);
                }
            },
        });
    };

    const postNote = () => {
        if (!noteForm.data.note.trim()) return;
        noteForm.post(routes.note ?? '', {
            preserveScroll: true,
            onSuccess: () => noteForm.reset('note'),
        });
    };

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            {flash.success ? (
                <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{flash.success}</div>
            ) : null}
            {flash.error ? (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{flash.error}</div>
            ) : null}
            {errors.claim ? (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">{errors.claim}</div>
            ) : null}
            {errors.review ? (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{errors.review}</div>
            ) : null}

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" size="sm" asChild>
                    <Link href={routes.index ?? '/admin/sellers'}>← Back to queue</Link>
                </Button>
                <div className="flex flex-wrap gap-2">
                    {flags.can_claim ? (
                        <Button type="button" variant="secondary" onClick={postClaim}>
                            Start review (claim)
                        </Button>
                    ) : null}
                    {flags.can_review && !flags.is_terminal ? (
                        <>
                            <Button type="button" onClick={() => setApproveOpen(true)}>
                                Approve
                            </Button>
                            <Button type="button" variant="destructive" onClick={() => postReview('rejected')}>
                                Reject case
                            </Button>
                        </>
                    ) : null}
                    {flags.is_terminal ? (
                        <Badge variant="muted" className="h-9 px-3 py-1">
                            Read-only · terminal outcome
                        </Badge>
                    ) : null}
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Card className="border-border/80 bg-card shadow-sm md:col-span-2">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Case operations</CardTitle>
                        <CardDescription>Claiming locks the case, review records the final outcome, and every action is audited.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-3">
                        <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Current state</p>
                            <p className="mt-1 text-sm font-semibold">{kyc.status ?? '—'}</p>
                        </div>
                        <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Reviewer</p>
                            <p className="mt-1 text-sm font-semibold">{kyc.reviewer_email ?? 'Unassigned'}</p>
                        </div>
                        <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assignee</p>
                            <p className="mt-1 text-sm font-semibold">{kyc.assigned_to_email ?? 'Unassigned'}</p>
                        </div>
                        <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">SLA</p>
                            <p className="mt-1 text-sm font-semibold">{kyc.escalated_at ? 'Escalated' : kyc.sla_warning_sent_at ? 'Warning sent' : 'On track'}</p>
                        </div>
                        <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">SLA due</p>
                            <p className="mt-1 text-sm font-semibold">{fmtDate(kyc.sla_due_at)}</p>
                        </div>
                        <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Automation</p>
                            <p className="mt-1 text-sm font-semibold">Notifications active</p>
                        </div>
                    </CardContent>
                </Card>
                <Card className="border-border/80 bg-card shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Workflow</CardTitle>
                        <CardDescription>Standard review sequence.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>1. Claim the case</p>
                        <p>2. Review evidence</p>
                        <p>3. Approve or reject</p>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <Card className="border-border/80 shadow-sm lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base">Seller summary</CardTitle>
                        <CardDescription>Identity, account, and case status in one view.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Seller</p>
                            <p className="text-sm font-semibold">{seller?.display_name ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Profile status</p>
                            <p className="text-sm font-semibold">{seller?.verification_status ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Account</p>
                            <p className="text-sm font-semibold">{account?.email ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Submitted</p>
                            <p className="text-sm font-semibold">{fmtDate(kyc.submitted_at)}</p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assigned</p>
                            <p className="text-sm font-semibold">{fmtDate(kyc.assigned_at)}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card className="border-border/80 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-base">Case note</CardTitle>
                        <CardDescription>Internal guidance for the operator.</CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Verify the uploaded documents, then keep the decision concise and policy-based.
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    <Card className="border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">Case summary</CardTitle>
                            <CardDescription>Immutable identifiers and lifecycle timestamps.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Case ID</p>
                                <p className="font-mono text-sm">#{kyc.id}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">UUID</p>
                                <p className="break-all font-mono text-xs text-muted-foreground">{kyc.uuid}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
                                <StatusBadge status={kyc.status} />
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Submitted</p>
                                <p className="text-sm">{fmtDate(kyc.submitted_at)}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Reviewed</p>
                                <p className="text-sm">{fmtDate(kyc.reviewed_at)}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Reviewer</p>
                                <p className="text-sm">{kyc.reviewer_email ?? '—'}</p>
                            </div>
                            {kyc.rejection_reason ? (
                                <div className="sm:col-span-2">
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Rejection reason</p>
                                    <p className="mt-1 rounded-md border border-border/80 bg-muted/30 p-3 text-sm">{kyc.rejection_reason}</p>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">Evidence bundle</CardTitle>
                            <CardDescription>
                                Authenticated same-origin downloads. Files must exist under the application disk and are normalized server-side.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!documents.length ? (
                                <p className="text-sm text-muted-foreground">No documents attached to this case.</p>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {documents.map((d) => (
                                        <div
                                            key={d.id}
                                            className="flex flex-col justify-between rounded-lg border border-border/80 bg-card p-4 shadow-xs"
                                        >
                                            <div>
                                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                    {String(d.doc_type).replace(/_/g, ' ')}
                                                </p>
                                                <div className="mt-2 flex items-center gap-2">
                                                    <StatusBadge status={d.status} />
                                                    <span className="font-mono text-[10px] text-muted-foreground">
                                                        sha256 {String(d.checksum_sha256).slice(0, 10)}…
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="mt-4">
                                                {d.download_url ? (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <a href={d.download_url} target="_blank" rel="noreferrer">
                                                            Secure download
                                                        </a>
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">File not on disk in this environment.</span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">Document intelligence</CardTitle>
                            <CardDescription>Lightweight quality checks based on the submitted evidence bundle.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Uploaded</p>
                                <p className="mt-1 text-sm font-semibold">{documentInsights.uploaded_count ?? 0}</p>
                            </div>
                            <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Verified</p>
                                <p className="mt-1 text-sm font-semibold">{documentInsights.verified_count ?? 0}</p>
                            </div>
                            <div className="rounded-lg border border-border/80 bg-muted/20 p-3">
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Quality</p>
                                <p className="mt-1 text-sm font-semibold">{documentInsights.quality_state ?? 'unknown'}</p>
                            </div>
                            <div className="sm:col-span-3 rounded-lg border border-border/80 bg-muted/20 p-3 text-sm text-muted-foreground">
                                {documentInsights.hint ?? 'Upload documents to unlock review insight.'}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card className="border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">Seller profile</CardTitle>
                            <CardDescription>Legal storefront identity (read-only in this workspace).</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {seller ? (
                                <>
                                    <div>
                                        <span className="text-muted-foreground">Display name</span>
                                        <p className="font-medium">{seller.display_name}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Legal name</span>
                                        <p>{seller.legal_name ?? '—'}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Country / currency</span>
                                        <p>
                                            {seller.country_code} · {seller.default_currency}
                                        </p>
                                    </div>
                                    <Separator />
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="outline">Profile: {seller.verification_status}</Badge>
                                        <Badge variant="outline">Store: {seller.store_status}</Badge>
                                    </div>
                                </>
                            ) : (
                                <p className="text-muted-foreground">Seller profile missing.</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">Linked account</CardTitle>
                            <CardDescription>Account that owns the seller profile.</CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {account ? (
                                <>
                                    <p className="font-medium">{account.email}</p>
                                    <p className="mt-1 font-mono text-xs text-muted-foreground">{account.uuid}</p>
                        </>
                    ) : (
                        <p className="text-muted-foreground">No linked user.</p>
                    )}
                </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">Internal notes</CardTitle>
                            <CardDescription>Private reviewer notes stay attached to the case.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="space-y-2">
                                <Textarea
                                    value={noteForm.data.note}
                                    onChange={(e) => noteForm.setData('note', e.target.value)}
                                    rows={4}
                                    placeholder="Add a concise review note..."
                                />
                                {noteForm.errors.note ? <p className="text-sm text-red-600">{noteForm.errors.note}</p> : null}
                            </div>
                            <Button type="button" variant="secondary" className="w-full" onClick={postNote} disabled={noteForm.processing || !noteForm.data.note.trim()}>
                                Add note
                            </Button>
                            <div className="max-h-[280px] space-y-2 overflow-auto pr-1">
                                {notes.length ? notes.map((note) => (
                                    <div key={note.id} className="rounded-lg border border-border/80 bg-muted/20 p-3 text-sm">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="font-medium">{note.author_email ?? '—'}</p>
                                            <p className="text-xs text-muted-foreground">{fmtDate(note.created_at)}</p>
                                        </div>
                                        <p className="mt-2 whitespace-pre-wrap text-muted-foreground">{note.note}</p>
                                    </div>
                                )) : (
                                    <p className="text-sm text-muted-foreground">No notes yet.</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {flags.can_review && !flags.is_terminal ? (
                        <Card className="border-border/80 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base">Reassign</CardTitle>
                                <CardDescription>Move this case to another reviewer with full audit tracking.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="space-y-2">
                                    <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground" htmlFor="kyc-reassign-to">
                                        Reviewer
                                    </label>
                                    <select
                                        id="kyc-reassign-to"
                                        value={reassignTo}
                                        onChange={(e) => setReassignTo(e.target.value)}
                                        className="h-10 w-full rounded-md border border-border/80 bg-background px-3 text-sm"
                                    >
                                        <option value="">Select reviewer</option>
                                        {reviewers.map((reviewer) => (
                                            <option key={reviewer.value} value={reviewer.value}>
                                                {reviewer.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button type="button" variant="secondary" className="w-full" onClick={postReassign} disabled={!reassignTo || reviewForm.processing}>
                                    Reassign case
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    Use this when the active reviewer changes or the queue needs manual balancing.
                                </p>
                            </CardContent>
                        </Card>
                    ) : null}

                    {flags.can_review && !flags.is_terminal ? (
                        <Card className="border-border/80 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base">Rejection rationale</CardTitle>
                                <CardDescription>Required for rejections; stored immutably in audit and case payload.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Textarea
                                    value={reviewForm.data.reason}
                                    onChange={(e) => reviewForm.setData('reason', e.target.value)}
                                    rows={5}
                                    placeholder="Reference missing or illegible evidence, policy breaches, or risk notes…"
                                />
                                {reviewForm.errors.reason ? <p className="text-sm text-red-600">{reviewForm.errors.reason}</p> : null}
                            </CardContent>
                        </Card>
                    ) : null}

                    {can('admin.audit.view') ? (
                        <Card className="border-dashed border-border/80 bg-muted/10">
                            <CardHeader>
                                <CardTitle className="text-base text-muted-foreground">Audit trail</CardTitle>
                                <CardDescription>Append-only entries on claim and final decision.</CardDescription>
                            </CardHeader>
                        </Card>
                    ) : null}
                </div>
            </div>

            <Card className="mt-8 border-border/80 shadow-sm">
                <CardHeader>
                    <CardTitle className="text-base">Decision history</CardTitle>
                    <CardDescription>Correlation IDs align UI actions with audit exports.</CardDescription>
                </CardHeader>
                <CardContent>
                    {!history.length ? (
                        <p className="text-sm text-muted-foreground">No audit events recorded for this case yet.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Actor</TableHead>
                                    <TableHead>Correlation</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {history.map((h) => (
                                    <TableRow key={h.id}>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">{fmtDate(h.created_at)}</TableCell>
                                        <TableCell className="font-mono text-xs">{h.action}</TableCell>
                                        <TableCell className="max-w-[160px] truncate text-sm">{h.actor_email ?? '—'}</TableCell>
                                        <TableCell className="font-mono text-[10px] text-muted-foreground">{h.correlation_id}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                open={approveOpen}
                onOpenChange={setApproveOpen}
                title="Approve verification?"
                description="Marks the seller as verified, finalizes supporting documents, and writes an audit record. Replays with the same decision are idempotent."
                confirmLabel="Approve"
                onConfirm={() => postReview('approved')}
            />
        </AdminLayout>
    );
}

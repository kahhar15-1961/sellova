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

    const flags = workspace?.flags ?? {};
    const kyc = workspace?.kyc ?? {};
    const seller = workspace?.seller ?? null;
    const account = workspace?.account ?? null;
    const documents = workspace?.documents ?? [];
    const history = workspace?.history ?? [];
    const routes = workspace?.routes ?? {};

    const postClaim = () => {
        router.post(routes.claim ?? '', {}, { preserveScroll: true });
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
                                Authenticated same-origin downloads. Files must exist under the application disk — paths are
                                normalized server-side.
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

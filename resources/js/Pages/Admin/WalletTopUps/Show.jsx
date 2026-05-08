import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

export default function WalletTopUpShow({ header, request, can_review, review_open, list_href, review_url }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};
    const fieldErrors = Object.entries(errors).filter(([k]) => k !== 'review');
    const reviewLocked = !review_open;

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4 flex flex-wrap gap-3">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Wallet top-ups</Link>
                </Button>
            </div>
            <div className="mb-4 rounded-xl border border-border/70 bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                Review this request with the buyer wallet context and payment reference before approving. Approval credits the wallet ledger immediately.
            </div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.review ? <p className="mb-4 text-sm text-destructive">{errors.review}</p> : null}
            {fieldErrors.length ? (
                <ul className="mb-4 list-inside list-disc text-sm text-destructive">
                    {fieldErrors.map(([k, v]) => (
                        <li key={k}>
                            {k}: {Array.isArray(v) ? v.join(', ') : v}
                        </li>
                    ))}
                </ul>
            ) : null}
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Request</CardTitle>
                        <CardDescription>Amounts and review state</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Status</p>
                                <div className="mt-1">
                                    <StatusBadge status={request.status} />
                                </div>
                            </div>
                            <div className="text-right">
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Requested</p>
                                <p className="text-lg font-semibold text-foreground">
                                    {request.currency} {request.requested_amount}
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                <p className="text-xs text-muted-foreground">Payment method</p>
                                <p className="mt-1 font-medium text-foreground">{request.payment_method || '—'}</p>
                            </div>
                            <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                <p className="text-xs text-muted-foreground">Reference</p>
                                <p className="mt-1 font-medium text-foreground">{request.payment_reference || '—'}</p>
                            </div>
                            <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                <p className="text-xs text-muted-foreground">Created</p>
                                <p className="mt-1 font-medium text-foreground">{request.created_at}</p>
                            </div>
                            <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                <p className="text-xs text-muted-foreground">Reviewed</p>
                                <p className="mt-1 font-medium text-foreground">{request.reviewed_at ?? '—'}</p>
                            </div>
                        </div>
                        {request.rejection_reason ? (
                            <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700">
                                <p className="text-xs uppercase tracking-wide">Reject reason</p>
                                <p className="mt-1">{request.rejection_reason}</p>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Wallet</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {request.wallet ? (
                            <>
                                <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                    <p className="text-xs text-muted-foreground">User</p>
                                    <p className="mt-1 font-medium text-foreground">{request.wallet.user_email ?? '—'}</p>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                        <p className="text-xs text-muted-foreground">Wallet</p>
                                        <p className="mt-1 font-medium text-foreground">#{request.wallet.id}</p>
                                    </div>
                                    <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                        <p className="text-xs text-muted-foreground">Type</p>
                                        <p className="mt-1 font-medium text-foreground">{request.wallet.type}</p>
                                    </div>
                                    <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                        <p className="text-xs text-muted-foreground">Currency</p>
                                        <p className="mt-1 font-medium text-foreground">{request.wallet.currency}</p>
                                    </div>
                                    <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                                        <p className="text-xs text-muted-foreground">Status</p>
                                        <p className="mt-1 font-medium text-foreground">{request.wallet.status}</p>
                                    </div>
                                </div>
                            </>
                        ) : null}
                        <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                            <p className="text-xs text-muted-foreground">Requested by</p>
                            <p className="mt-1 font-medium text-foreground">{request.requested_by_email ?? '—'}</p>
                        </div>
                        <div className="rounded-lg border border-border/70 bg-background px-3 py-2">
                            <p className="text-xs text-muted-foreground">Reviewer</p>
                            <p className="mt-1 font-medium text-foreground">{request.reviewer_email ?? '—'}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
            {can_review ? (
                <Card className="mt-8 border-amber-200/80 bg-amber-50/40">
                    <CardHeader>
                        <CardTitle>Finance review</CardTitle>
                        <CardDescription>
                            Approves or rejects this wallet top-up. Approval posts the credit to the real wallet ledger.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {reviewLocked ? (
                            <p className="mb-4 rounded-lg border border-border/70 bg-background px-3 py-2 text-sm text-muted-foreground">
                                This request is already reviewed. The decision is locked for audit safety.
                            </p>
                        ) : null}
                        <Form action={review_url} method="post" className="space-y-4">
                            <div className="space-y-2">
                                <label htmlFor="decision" className="text-sm font-medium">
                                    Decision
                                </label>
                                <select
                                    id="decision"
                                    name="decision"
                                    required
                                    className="flex h-10 w-full max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                                    defaultValue=""
                                    disabled={reviewLocked}
                                >
                                    <option value="" disabled>
                                        Select…
                                    </option>
                                    <option value="approved">Approve (credit wallet)</option>
                                    <option value="rejected">Reject (no credit)</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label htmlFor="reason" className="text-sm font-medium">
                                    Reason (required if rejecting)
                                </label>
                                <Textarea
                                    id="reason"
                                    name="reason"
                                    placeholder="Explain rejection for audit trail"
                                    className="min-h-24"
                                    disabled={reviewLocked}
                                />
                            </div>
                            <Button type="submit" disabled={reviewLocked}>
                                Submit review
                            </Button>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}
        </AdminLayout>
    );
}

import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function WithdrawalShow({ header, withdrawal, can_review, review_open, list_href, review_url }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};
    const fieldErrors = Object.entries(errors).filter(([k]) => k !== 'review');

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4 flex flex-wrap gap-3">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Withdrawals</Link>
                </Button>
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
                        <CardDescription>Amounts and lifecycle</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>
                            Status: <StatusBadge status={withdrawal.status} />
                        </p>
                        <p>
                            Requested: {withdrawal.currency} {withdrawal.requested_amount}
                        </p>
                        <p>
                            Net payout: {withdrawal.currency} {withdrawal.net_payout_amount}
                        </p>
                        <p className="text-muted-foreground">Created: {withdrawal.created_at}</p>
                        <p className="text-muted-foreground">Reviewed: {withdrawal.reviewed_at ?? '—'}</p>
                        {withdrawal.reject_reason ? (
                            <p className="text-destructive">Reject reason: {withdrawal.reject_reason}</p>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Parties</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>Seller: {withdrawal.seller_display ?? '—'}</p>
                        <p className="text-muted-foreground">{withdrawal.seller_user_email ?? ''}</p>
                        <p>Reviewer: {withdrawal.reviewer_email ?? '—'}</p>
                        {withdrawal.wallet ? (
                            <p className="pt-2 text-muted-foreground">
                                Wallet #{withdrawal.wallet.id} · {withdrawal.wallet.type} · {withdrawal.wallet.currency} ·{' '}
                                {withdrawal.wallet.status}
                            </p>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
            {can_review && review_open ? (
                <Card className="mt-8 border-amber-200/80 bg-amber-50/40">
                    <CardHeader>
                        <CardTitle>Finance review</CardTitle>
                        <CardDescription>
                            Approves or rejects this withdrawal using the domain withdrawal service (ledger settlement on
                            approve).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action={review_url} method="post" className="space-y-4">
                            <div className="space-y-2">
                                <label htmlFor="decision" className="text-sm font-medium">
                                    Decision
                                </label>
                                <select
                                    id="decision"
                                    name="decision"
                                    required
                                    className="flex h-10 w-full max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    defaultValue=""
                                >
                                    <option value="" disabled>
                                        Select…
                                    </option>
                                    <option value="approved">Approve (settle from hold)</option>
                                    <option value="rejected">Reject (release hold)</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label htmlFor="reason" className="text-sm font-medium">
                                    Reason (required if rejecting)
                                </label>
                                <Input id="reason" name="reason" placeholder="Explain rejection for audit trail" />
                            </div>
                            <Button type="submit">Submit review</Button>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}
        </AdminLayout>
    );
}

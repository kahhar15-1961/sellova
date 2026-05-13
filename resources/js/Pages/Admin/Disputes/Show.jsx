import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function DisputeShow({
    header,
    dispute,
    escrow,
    evidence,
    decision,
    can_move_to_review,
    can_resolve,
    list_href,
    move_to_review_url,
    resolve_url,
}) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};
    const fieldErrors = Object.entries(errors).filter(([k]) => k !== 'dispute');

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Disputes</Link>
                </Button>
            </div>
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.dispute ? <p className="mb-4 text-sm text-destructive">{errors.dispute}</p> : null}
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
                        <CardTitle>Case</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>
                            Status: <StatusBadge status={dispute.status} />
                        </p>
                        <p>Order: {dispute.order_number ?? '—'}</p>
                        <p>Buyer: {dispute.buyer_email ?? '—'}</p>
                        <p>Opened by: {dispute.opened_by_email ?? '—'}</p>
                        <p className="text-muted-foreground">Opened: {dispute.opened_at}</p>
                        {dispute.resolution_outcome ? <p>Outcome: {dispute.resolution_outcome}</p> : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Escrow</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {!escrow ? (
                            <p className="text-muted-foreground">No escrow row.</p>
                        ) : (
                            <ul className="space-y-1">
                                <li>State: {escrow.state}</li>
                                <li>
                                    Held: {formatMoney(escrow.held_amount, escrow.currency, { currencyDisplay: 'code' })}
                                </li>
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
            {can_move_to_review ? (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Workflow</CardTitle>
                        <CardDescription>Move from opened / evidence collection into formal review.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action={move_to_review_url} method="post">
                            <Button type="submit">Move to under review</Button>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}
            {can_resolve ? (
                <Card className="mt-6 border-amber-200/80 bg-amber-50/40">
                    <CardHeader>
                        <CardTitle>Resolve (full remaining)</CardTitle>
                        <CardDescription>
                            Buyer-wins refunds the full remaining escrow to the buyer; seller-wins releases the remainder to
                            the seller. Requires escrow state “under dispute”.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action={resolve_url} method="post" className="space-y-4">
                            <div className="space-y-2">
                                <label htmlFor="resolution" className="text-sm font-medium">
                                    Resolution
                                </label>
                                <select
                                    id="resolution"
                                    name="resolution"
                                    required
                                    className="flex h-10 w-full max-w-md rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    defaultValue=""
                                >
                                    <option value="" disabled>
                                        Select…
                                    </option>
                                    <option value="buyer_wins">Buyer wins (full remaining to buyer)</option>
                                    <option value="seller_wins">Seller wins (full remaining to seller)</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label htmlFor="reason_code" className="text-sm font-medium">
                                    Reason code
                                </label>
                                <Input id="reason_code" name="reason_code" required placeholder="e.g. policy_violation" />
                            </div>
                            <div className="space-y-2">
                                <label htmlFor="notes" className="text-sm font-medium">
                                    Decision notes (min 10 chars)
                                </label>
                                <Textarea id="notes" name="notes" required rows={5} minLength={10} />
                            </div>
                            <Button type="submit">Submit resolution</Button>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}
            {decision ? (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Recorded decision</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        <p>Outcome: {decision.outcome}</p>
                        <p>
                            Buyer: {formatMoney(decision.buyer_amount, decision.currency, { currencyDisplay: 'code' })} · Seller:{' '}
                            {formatMoney(decision.seller_amount, decision.currency, { currencyDisplay: 'code' })}
                        </p>
                        <p>Reason: {decision.reason_code}</p>
                        <p className="text-muted-foreground">Decided: {decision.decided_at}</p>
                    </CardContent>
                </Card>
            ) : null}
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Evidence</CardTitle>
                </CardHeader>
                <CardContent>
                    {!evidence?.length ? (
                        <p className="text-sm text-muted-foreground">No evidence rows.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead>Preview</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {evidence.map((e) => (
                                    <TableRow key={e.id}>
                                        <TableCell>{e.type}</TableCell>
                                        <TableCell className="text-muted-foreground">{e.submitted_at}</TableCell>
                                        <TableCell className="max-w-md truncate">{e.preview}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}

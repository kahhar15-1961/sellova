import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

function money(currency, amount) {
    const formatted = Number.parseFloat(String(amount ?? 0)).toFixed(2);
    return `${currency || ''} ${formatted}`.trim();
}

export default function EscrowShow({ header, escrow, disputes, events, can_manage, action_url, list_href, reason_codes }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};

    const disputeOptions = disputes || [];
    const activeDispute = disputeOptions.find((d) => d.status === 'opened' || d.status === 'assigned' || d.status === 'escalated' || d.status === 'under_review') || disputeOptions[0] || null;

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="mb-4 flex flex-wrap gap-3">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Escrows</Link>
                </Button>
                {escrow.order?.href ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={escrow.order.href}>Open order</Link>
                    </Button>
                ) : null}
            </div>
            {flash.success ? <p className="mb-4 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700">{flash.success}</p> : null}
            {errors.action ? <p className="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{errors.action}</p> : null}

            <div className="grid gap-6 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Escrow summary</CardTitle>
                        <CardDescription>Order-linked hold, release, and refund balances</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2 text-sm">
                            <p><span className="text-muted-foreground">State:</span> <StatusBadge status={escrow.state} /></p>
                            <p><span className="text-muted-foreground">Held:</span> {money(escrow.currency, escrow.held_amount)}</p>
                            <p><span className="text-muted-foreground">Released:</span> {money(escrow.currency, escrow.released_amount)}</p>
                            <p><span className="text-muted-foreground">Refunded:</span> {money(escrow.currency, escrow.refunded_amount)}</p>
                        </div>
                        <div className="space-y-2 text-sm">
                            <p><span className="text-muted-foreground">Held at:</span> {fmtDate(escrow.held_at)}</p>
                            <p><span className="text-muted-foreground">Closed at:</span> {fmtDate(escrow.closed_at)}</p>
                            <p><span className="text-muted-foreground">Version:</span> {escrow.version}</p>
                            {escrow.order ? (
                                <p>
                                    <span className="text-muted-foreground">Order:</span>{' '}
                                    <Link href={escrow.order.href} className="font-medium text-primary hover:underline">
                                        {escrow.order.order_number}
                                    </Link>
                                </p>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Parties</CardTitle>
                        <CardDescription>Buyer, seller, and storefront context</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {escrow.buyer ? (
                            <p>
                                Buyer:{' '}
                                <Link href={escrow.buyer.href} className="font-medium text-primary hover:underline">
                                    {escrow.buyer.email}
                                </Link>
                            </p>
                        ) : <p className="text-muted-foreground">No buyer linked.</p>}
                        {escrow.seller ? (
                            <div className="space-y-1">
                                <p>
                                    Seller:{' '}
                                    <Link href={escrow.seller.href} className="font-medium text-primary hover:underline">
                                        {escrow.seller.display_name ?? `#${escrow.seller.id}`}
                                    </Link>
                                </p>
                                {escrow.seller.storefront ? (
                                    <p className="text-muted-foreground">
                                        Storefront: {escrow.seller.storefront.title ?? '—'} ·{' '}
                                        {escrow.seller.storefront.is_public ? 'Public' : 'Private'}
                                    </p>
                                ) : null}
                            </div>
                        ) : <p className="text-muted-foreground">No seller linked.</p>}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Disputes</CardTitle>
                        <CardDescription>Linked disputes and escalation context</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!disputeOptions.length ? (
                            <p className="text-sm text-muted-foreground">No disputes linked to this order.</p>
                        ) : (
                            <div className="space-y-2">
                                {disputeOptions.map((dispute) => (
                                    <div key={dispute.id} className="rounded-md border p-3 text-sm">
                                        <p className="font-medium">
                                            <Link href={dispute.href} className="text-primary hover:underline">
                                                Dispute #{dispute.id}
                                            </Link>
                                        </p>
                                        <p className="text-muted-foreground">Status: {dispute.status}</p>
                                        <p className="text-muted-foreground">Opened by: {dispute.opened_by}</p>
                                        <p className="text-muted-foreground">Opened at: {fmtDate(dispute.opened_at)}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Escrow events</CardTitle>
                        <CardDescription>Immutable settlement timeline</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!events?.length ? (
                            <p className="text-sm text-muted-foreground">No escrow events recorded.</p>
                        ) : (
                            <div className="space-y-2">
                                {events.map((event) => (
                                    <div key={event.id} className="rounded-md border p-3 text-sm">
                                        <p className="font-medium">{event.type}</p>
                                        <p className="text-muted-foreground">
                                            {event.from_state || '—'} → {event.to_state || '—'} · {money(escrow.currency, event.amount)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {event.reference_type ?? '—'} #{event.reference_id ?? '—'} · {fmtDate(event.created_at)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {can_manage ? (
                <div className="mt-6 grid gap-6 xl:grid-cols-2">
                    <Card className="border-emerald-200/70 bg-emerald-50/40">
                        <CardHeader>
                            <CardTitle>Approve / release</CardTitle>
                            <CardDescription>Release held funds to the seller when settlement is approved.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form action={action_url} method="post" className="space-y-3">
                                <input type="hidden" name="action" value="release" />
                                <div>
                                    <label className="mb-1 block text-sm font-medium" htmlFor="release_reason_code">Reason code</label>
                                    <select id="release_reason_code" name="reason_code" required className="h-10 w-full rounded-md border px-3 text-sm" defaultValue="">
                                        <option value="" disabled>Select reason</option>
                                        {(reason_codes || []).map((code) => <option key={code} value={code}>{code}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium" htmlFor="release_notes">Notes</label>
                                    <Textarea id="release_notes" name="notes" rows={3} placeholder="Why is this escrow being released?" />
                                </div>
                                <Button type="submit">Release escrow</Button>
                            </Form>
                        </CardContent>
                    </Card>

                    <Card className="border-amber-200/70 bg-amber-50/40">
                        <CardHeader>
                            <CardTitle>Refund</CardTitle>
                            <CardDescription>Refund all or part of the remaining held amount back to the buyer.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form action={action_url} method="post" className="space-y-3">
                                <input type="hidden" name="action" value="refund" />
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium" htmlFor="refund_amount">Refund amount</label>
                                        <Input id="refund_amount" name="refund_amount" placeholder="Leave blank for full remaining" />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium" htmlFor="refund_reason_code">Reason code</label>
                                        <select id="refund_reason_code" name="reason_code" required className="h-10 w-full rounded-md border px-3 text-sm" defaultValue="">
                                            <option value="" disabled>Select reason</option>
                                            {(reason_codes || []).map((code) => <option key={code} value={code}>{code}</option>)}
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium" htmlFor="refund_notes">Notes</label>
                                    <Textarea id="refund_notes" name="notes" rows={3} placeholder="Explain why the buyer is being refunded." />
                                </div>
                                <Button type="submit">Refund escrow</Button>
                            </Form>
                        </CardContent>
                    </Card>

                    {disputeOptions.length > 0 ? (
                        <>
                            <Card className="border-sky-200/70 bg-sky-50/40">
                                <CardHeader>
                                    <CardTitle>Mark under dispute</CardTitle>
                                    <CardDescription>Freeze escrow while a dispute is being managed.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form action={action_url} method="post" className="space-y-3">
                                        <input type="hidden" name="action" value="dispute" />
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-sm font-medium" htmlFor="dispute_case_id">Dispute case</label>
                                                <select id="dispute_case_id" name="dispute_case_id" required className="h-10 w-full rounded-md border px-3 text-sm" defaultValue={activeDispute ? String(activeDispute.id) : ''}>
                                                    <option value="" disabled>{activeDispute ? `Use dispute #${activeDispute.id}` : 'Select a dispute'}</option>
                                                    {disputeOptions.map((dispute) => <option key={dispute.id} value={dispute.id}>#{dispute.id} · {dispute.status}</option>)}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium" htmlFor="dispute_reason_code">Reason code</label>
                                                <select id="dispute_reason_code" name="reason_code" required className="h-10 w-full rounded-md border px-3 text-sm" defaultValue="">
                                                    <option value="" disabled>Select reason</option>
                                                    {(reason_codes || []).map((code) => <option key={code} value={code}>{code}</option>)}
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium" htmlFor="dispute_notes">Notes</label>
                                            <Textarea id="dispute_notes" name="notes" rows={3} placeholder="State why the escrow is moving under dispute." />
                                        </div>
                                        <Button type="submit">Mark under dispute</Button>
                                    </Form>
                                </CardContent>
                            </Card>

                            <Card className="border-violet-200/70 bg-violet-50/40">
                                <CardHeader>
                                    <CardTitle>Settle dispute</CardTitle>
                                    <CardDescription>Split the remaining hold between buyer refund and seller release.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form action={action_url} method="post" className="space-y-3">
                                        <input type="hidden" name="action" value="settle" />
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-sm font-medium" htmlFor="settle_dispute_case_id">Dispute case</label>
                                                <select id="settle_dispute_case_id" name="dispute_case_id" required className="h-10 w-full rounded-md border px-3 text-sm" defaultValue={activeDispute ? String(activeDispute.id) : ''}>
                                                    <option value="" disabled>{activeDispute ? `Use dispute #${activeDispute.id}` : 'Select a dispute'}</option>
                                                    {disputeOptions.map((dispute) => <option key={dispute.id} value={dispute.id}>#{dispute.id} · {dispute.status}</option>)}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium" htmlFor="buyer_refund_amount">Buyer refund</label>
                                                <Input id="buyer_refund_amount" name="buyer_refund_amount" placeholder="0.0000" />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium" htmlFor="seller_release_amount">Seller release</label>
                                                <Input id="seller_release_amount" name="seller_release_amount" placeholder="0.0000" />
                                            </div>
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium" htmlFor="settle_reason_code">Reason code</label>
                                            <select id="settle_reason_code" name="reason_code" required className="h-10 w-full rounded-md border px-3 text-sm" defaultValue="">
                                                <option value="" disabled>Select reason</option>
                                                {(reason_codes || []).map((code) => <option key={code} value={code}>{code}</option>)}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium" htmlFor="settle_notes">Notes</label>
                                            <Textarea id="settle_notes" name="notes" rows={3} placeholder="Explain the dispute split and resolution rationale." />
                                        </div>
                                        <Button type="submit">Settle dispute</Button>
                                    </Form>
                                </CardContent>
                            </Card>
                        </>
                    ) : (
                        <Card className="border-slate-200/70 bg-slate-50/40">
                            <CardHeader>
                                <CardTitle>Dispute settlement</CardTitle>
                                <CardDescription>This order currently has no linked dispute case, so dispute actions are hidden.</CardDescription>
                            </CardHeader>
                        </Card>
                    )}
                </div>
            ) : null}
        </AdminLayout>
    );
}

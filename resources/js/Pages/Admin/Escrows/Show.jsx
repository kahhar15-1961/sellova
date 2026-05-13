import { useEffect, useMemo, useState } from 'react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/utils';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

function money(currency, amount) {
    return formatMoney(amount, currency, { currencyDisplay: 'code' });
}

function toInputDateTime(value) {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const offsetMinutes = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offsetMinutes * 60000);
    return local.toISOString().slice(0, 16);
}

function humanize(value) {
    return String(value || '—')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatBytes(bytes) {
    const size = Number(bytes || 0);
    if (size <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1);
    const value = size / (1024 ** exponent);
    return `${value.toFixed(value >= 10 || exponent === 0 ? 0 : 1)} ${units[exponent]}`;
}

function formatCountdown(totalSeconds) {
    const seconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remaining = seconds % 60;

    return [hours, minutes, remaining]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

function DeadlineCard({ label, value, accent = 'slate' }) {
    const accentClasses = {
        slate: 'border-slate-200 bg-slate-50/80',
        indigo: 'border-indigo-200 bg-indigo-50/70',
        amber: 'border-amber-200 bg-amber-50/80',
    };

    return (
        <div className={`rounded-xl border p-4 ${accentClasses[accent] || accentClasses.slate}`}>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">{label}</p>
            <p className="mt-2 text-sm font-semibold text-foreground">{fmtDate(value)}</p>
        </div>
    );
}

export default function EscrowShow({ header, escrow, disputes, events, can_manage, action_url, list_href, reason_codes, delivery = null, chat = null }) {
    const page = usePage();
    const errors = page.props.errors || {};
    const flash = page.props.flash || {};
    const disputeOptions = disputes || [];
    const activeDispute = disputeOptions.find((d) => ['opened', 'assigned', 'escalated', 'under_review'].includes(d.status)) || disputeOptions[0] || null;
    const [countdown, setCountdown] = useState(null);

    useEffect(() => {
        const expiresAt = escrow?.expires_at ? new Date(escrow.expires_at).getTime() : null;
        if (!expiresAt) {
            setCountdown(null);
            return undefined;
        }

        const update = () => {
            setCountdown(Math.max(0, Math.floor((expiresAt - Date.now()) / 1000)));
        };

        update();
        const timer = window.setInterval(update, 1000);
        return () => window.clearInterval(timer);
    }, [escrow?.expires_at]);

    const deliveryFiles = delivery?.files || [];
    const chatMessages = chat?.messages || [];
    const hasExpiry = countdown !== null;
    const isExpiringSoon = hasExpiry && countdown <= 6 * 3600 && countdown > 0;

    const summaryRows = useMemo(() => ([
        ['State', <StatusBadge key="state" status={escrow.state} />],
        ['Held', money(escrow.currency, escrow.held_amount)],
        ['Released', money(escrow.currency, escrow.released_amount)],
        ['Refunded', money(escrow.currency, escrow.refunded_amount)],
        ['Started at', fmtDate(escrow.started_at || escrow.held_at)],
        ['Released at', fmtDate(escrow.released_at)],
        ['Closed at', fmtDate(escrow.closed_at)],
        ['Version', escrow.version],
    ]), [escrow]);

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

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.9fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Escrow overview</CardTitle>
                        <CardDescription>Settlement state, deadline visibility, and order-linked review metadata.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            {summaryRows.map(([label, value]) => (
                                <div key={label} className="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">{label}</p>
                                    <div className="mt-2 font-semibold text-foreground">{value}</div>
                                </div>
                            ))}
                        </div>

                        <div className="rounded-2xl border border-slate-900 bg-slate-950 p-5 text-white shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Escrow countdown</p>
                                    <p className={`mt-3 text-4xl font-semibold tracking-tight ${isExpiringSoon ? 'text-amber-300' : 'text-white'}`}>
                                        {hasExpiry ? formatCountdown(countdown) : '--:--:--'}
                                    </p>
                                    <p className="mt-2 text-sm text-slate-300">
                                        {countdown === 0 ? 'Escrow expiry reached. Review auto-release or admin action rules.' : 'Server-driven escrow expiry based on stored deadline.'}
                                    </p>
                                </div>
                                <div className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">
                                    {escrow.order?.status ? humanize(escrow.order.status) : 'Order linked'}
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <DeadlineCard label="Escrow expires" value={escrow.expires_at} accent={isExpiringSoon ? 'amber' : 'indigo'} />
                            <DeadlineCard label="Delivery deadline" value={escrow.delivery_deadline_at} />
                            <DeadlineCard label="Dispute deadline" value={escrow.dispute_deadline_at || escrow.order?.buyer_review_expires_at} />
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Parties</CardTitle>
                            <CardDescription>Buyer, seller, and storefront context</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {escrow.buyer ? (
                                <p>
                                    Buyer:{' '}
                                    <span className="inline-flex flex-col align-top">
                                        <Link href={escrow.buyer.href} className="font-medium text-primary hover:underline">
                                            {escrow.buyer.name ?? `Buyer #${escrow.buyer.id}`}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">{escrow.buyer.email ?? 'No email'}</span>
                                    </span>
                                </p>
                            ) : <p className="text-muted-foreground">No buyer linked.</p>}
                            {escrow.seller ? (
                                <div className="space-y-1">
                                    <p>
                                        Seller:{' '}
                                        <span className="inline-flex flex-col align-top">
                                            <Link href={escrow.seller.href} className="font-medium text-primary hover:underline">
                                                {escrow.seller.display_name ?? `Seller #${escrow.seller.id}`}
                                            </Link>
                                            <span className="text-xs text-muted-foreground">{escrow.seller.account_email ?? 'No email'}</span>
                                        </span>
                                    </p>
                                    {escrow.seller.storefront ? (
                                        <p className="text-muted-foreground">
                                            Storefront: {escrow.seller.storefront.title ?? '—'} · {escrow.seller.storefront.is_public ? 'Public' : 'Private'}
                                        </p>
                                    ) : null}
                                </div>
                            ) : <p className="text-muted-foreground">No seller linked.</p>}
                            {escrow.order ? (
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Order detail</p>
                                    <p className="mt-2 font-semibold text-foreground">{escrow.order.order_number}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">Placed {fmtDate(escrow.order.placed_at)}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">Delivery status: {humanize(escrow.order.delivery_status)}</p>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

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
                                            <p className="text-muted-foreground">Status: {humanize(dispute.status)}</p>
                                            <p className="text-muted-foreground">Opened by: {dispute.opened_by}</p>
                                            <p className="text-muted-foreground">Opened at: {fmtDate(dispute.opened_at)}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Digital delivery</CardTitle>
                        <CardDescription>Seller delivery payload, revision state, and secure file access.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {!delivery ? (
                            <p className="text-sm text-muted-foreground">No digital delivery has been submitted yet.</p>
                        ) : (
                            <>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Status</p>
                                        <div className="mt-2"><StatusBadge status={delivery.status} /></div>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Version</p>
                                        <p className="mt-2 font-semibold text-foreground">{delivery.version || '—'}</p>
                                    </div>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Delivered at</p>
                                    <p className="mt-2 font-semibold text-foreground">{fmtDate(delivery.delivered_at)}</p>
                                    {delivery.external_url ? (
                                        <p className="mt-3">
                                            <a href={delivery.external_url} target="_blank" rel="noreferrer" className="font-medium text-primary hover:underline">
                                                Open external delivery URL
                                            </a>
                                        </p>
                                    ) : null}
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Delivery note</p>
                                    <p className="mt-2 whitespace-pre-wrap font-medium text-foreground">{delivery.note || 'No delivery note was provided.'}</p>
                                </div>
                                <div className="space-y-2">
                                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Files</p>
                                    {deliveryFiles.length ? deliveryFiles.map((file) => (
                                        <div key={file.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                            <div>
                                                <p className="font-semibold text-foreground">{file.name}</p>
                                                <p className="text-muted-foreground">{file.mime_type || 'application/octet-stream'} · {formatBytes(file.size_bytes)}</p>
                                            </div>
                                            <Button variant="outline" size="sm" asChild>
                                                <a href={file.download_url}>Download</a>
                                            </Button>
                                        </div>
                                    )) : <p className="text-sm text-muted-foreground">No secure files were uploaded for this delivery.</p>}
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Escrow chat log</CardTitle>
                        <CardDescription>Buyer, seller, and system notices captured on the escrow thread.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {!chatMessages.length ? (
                            <p className="text-sm text-muted-foreground">No escrow chat messages recorded yet.</p>
                        ) : (
                            chatMessages.map((message) => (
                                <div key={message.id} className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-semibold text-foreground">{humanize(message.sender_role)}</p>
                                        <p className="text-xs text-muted-foreground">{fmtDate(message.created_at)}</p>
                                    </div>
                                    {message.marker_type ? (
                                        <p className="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600">{humanize(message.marker_type)}</p>
                                    ) : null}
                                    <p className="mt-2 whitespace-pre-wrap text-foreground">{message.body || 'System event recorded.'}</p>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
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
                                        <p className="font-medium">{humanize(event.type)}</p>
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

                {can_manage ? (
                    <Card className="border-indigo-200/70 bg-indigo-50/40">
                        <CardHeader>
                            <CardTitle>Extend deadlines</CardTitle>
                            <CardDescription>Adjust escrow, delivery, or dispute deadlines without changing financial state.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form action={action_url} method="post" className="space-y-3">
                                <input type="hidden" name="action" value="extend_deadline" />
                                <div className="grid gap-3">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium" htmlFor="escrow_expires_at">Escrow expires at</label>
                                        <Input id="escrow_expires_at" type="datetime-local" name="escrow_expires_at" defaultValue={toInputDateTime(escrow.expires_at)} />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium" htmlFor="delivery_deadline_at">Delivery deadline</label>
                                        <Input id="delivery_deadline_at" type="datetime-local" name="delivery_deadline_at" defaultValue={toInputDateTime(escrow.delivery_deadline_at)} />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium" htmlFor="dispute_deadline_at">Dispute deadline</label>
                                        <Input id="dispute_deadline_at" type="datetime-local" name="dispute_deadline_at" defaultValue={toInputDateTime(escrow.dispute_deadline_at || escrow.order?.buyer_review_expires_at)} />
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium" htmlFor="extend_reason_code">Reason code</label>
                                    <select id="extend_reason_code" name="reason_code" required className="h-10 w-full rounded-md border px-3 text-sm" defaultValue="">
                                        <option value="" disabled>Select reason</option>
                                        {(reason_codes || []).map((code) => <option key={code} value={code}>{code}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium" htmlFor="extend_notes">Notes</label>
                                    <Textarea id="extend_notes" name="notes" rows={3} placeholder="Why are these deadlines being adjusted?" />
                                </div>
                                <Button type="submit">Save deadline changes</Button>
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}
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

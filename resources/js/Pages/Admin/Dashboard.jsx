import { Head, Link, router, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatCard } from '@/components/admin/StatCard';
import { DashboardQueuePanel } from '@/components/admin/DashboardQueuePanel';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/utils';

const STAT_DEFS = [
    { key: 'total_users', label: 'Total users' },
    { key: 'total_sellers', label: 'Total sellers' },
    { key: 'pending_seller_verifications', label: 'Pending seller verifications' },
    { key: 'total_products', label: 'Total products' },
    { key: 'total_orders', label: 'Total orders' },
    { key: 'orders_in_escrow', label: 'Orders in escrow' },
    { key: 'open_disputes', label: 'Open disputes' },
    { key: 'pending_withdrawals', label: 'Pending withdrawals' },
    { key: 'pending_wallet_top_ups', label: 'Wallet top-ups' },
    { key: 'escalated_cases', label: 'Escalated cases' },
    { key: 'total_gmv', label: 'Total GMV' },
    { key: 'released_funds', label: 'Released funds' },
    { key: 'refunded_funds', label: 'Refunded funds' },
];

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
}

function fmtMoney(amount, currency) {
    return formatMoney(amount, currency, { currencyDisplay: 'code' });
}

function alertVariant(sev) {
    if (sev === 'danger') return 'danger';
    if (sev === 'warning') return 'warning';
    if (sev === 'success') return 'success';
    return 'secondary';
}

function TrendBars({ title, series }) {
    const max = Math.max(1, ...(series || []).map((s) => Number(s.value || 0)));

    return (
        <Card className="panel-card">
            <CardHeader className="pb-2">
                <CardTitle className="text-base dark:text-slate-100">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-7 gap-1 sm:grid-cols-10">
                    {(series || []).map((point) => {
                        const h = Math.max(8, Math.round((Number(point.value || 0) / max) * 56));
                        return (
                            <div key={point.label} className="flex flex-col items-center gap-1">
                                <div className="w-full rounded-sm bg-primary/15" style={{ height: '56px' }}>
                                    <div className="w-full rounded-sm bg-primary" style={{ height: `${h}px`, marginTop: `${56 - h}px` }} />
                                </div>
                                <span className="text-[10px] text-muted-foreground">{point.label}</span>
                                <span className="text-[10px] font-medium tabular-nums">{point.value}</span>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    header,
    summary,
    recent_orders: recentOrders,
    open_disputes: openDisputes,
    pending_withdrawals: pendingWithdrawals,
    pending_wallet_top_ups: pendingWalletTopUps,
    seller_verification_queue: sellerVerificationQueue,
    product_moderation: productModeration,
    system_alerts: systemAlerts,
    section_access: sectionAccess,
    links,
    trend_range: trendRange,
    trends,
}) {
    const page = usePage();
    const authEmail = page.props.auth?.user?.email ?? '';

    const setRange = (range) => {
        router.get('/admin/dashboard', { range }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AdminLayout className="bg-[radial-gradient(circle_at_top,_rgba(148,163,184,0.18),transparent_32%),linear-gradient(180deg,#f8fafc_0%,#eef2f7_100%)] dark:bg-[radial-gradient(circle_at_top,_rgba(51,65,85,0.48),transparent_28%),linear-gradient(180deg,#111827_0%,#0f172a_100%)]">
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            <div className="mb-6 flex flex-col gap-1 rounded-[1.15rem] border border-border/60 bg-card/85 px-4 py-3 shadow-sm backdrop-blur dark:border-slate-700/80 dark:bg-slate-800/80 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Signed in</p>
                    <p className="text-sm font-semibold text-foreground">{authEmail || 'Admin'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant={trendRange === '24h' ? 'default' : 'outline'} size="sm" onClick={() => setRange('24h')}>24h</Button>
                    <Button variant={trendRange === '7d' ? 'default' : 'outline'} size="sm" onClick={() => setRange('7d')}>7d</Button>
                    <Button variant={trendRange === '30d' ? 'default' : 'outline'} size="sm" onClick={() => setRange('30d')}>30d</Button>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                {STAT_DEFS.map(({ key, label }) => {
                    const cell = summary?.[key] ?? { value: null, hint: null };
                    const locked = cell.value === null || cell.value === undefined;
                    return <StatCard key={key} label={label} value={cell.value} hint={cell.hint ?? undefined} locked={locked} variant="dashboard" />;
                })}
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <TrendBars title="Orders trend" series={trends?.orders || []} />
                <TrendBars title="Disputes trend" series={trends?.disputes || []} />
                <TrendBars title="Withdrawals trend" series={trends?.withdrawals || []} />
            </div>

            <Tabs defaultValue="queues" className="mt-10">
                <TabsList className="grid w-full max-w-md grid-cols-2">
                    <TabsTrigger value="queues">Queues & alerts</TabsTrigger>
                    <TabsTrigger value="layout">Layout</TabsTrigger>
                </TabsList>

                <TabsContent value="queues" className="mt-6 space-y-8">
                    <section>
                        <div className="mb-4 flex items-end justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold tracking-tight text-foreground dark:text-slate-100">Operational queues</h2>
                                <p className="text-sm text-muted-foreground dark:text-slate-400">Prioritized slices with bounded rows and eager relations.</p>
                            </div>
                        </div>
                        <div className="grid gap-6 lg:grid-cols-2">
                            <DashboardQueuePanel title="Recent orders" description="Latest placed orders with buyer context." href={links.orders} locked={!sectionAccess.recent_orders}>
                                {!recentOrders?.length ? <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground">No recent orders in range.</p> : (
                                    <Table><TableHeader><TableRow><TableHead>Order</TableHead><TableHead>Buyer</TableHead><TableHead>Total</TableHead><TableHead>Status</TableHead><TableHead>Placed</TableHead></TableRow></TableHeader><TableBody>{recentOrders.map((row) => <TableRow key={row.id}><TableCell className="font-medium tabular-nums">{row.order_number}</TableCell><TableCell className="max-w-[180px]"><p className="truncate font-medium text-foreground">{row.buyer_name ?? `Buyer #${row.buyer_user_id ?? row.id}`}</p><p className="truncate text-xs text-muted-foreground">{row.buyer_email ?? 'No email'}</p></TableCell><TableCell className="tabular-nums">{fmtMoney(row.gross_amount, row.currency)}</TableCell><TableCell><StatusBadge status={row.status} /></TableCell><TableCell className="text-muted-foreground">{fmtDate(row.placed_at)}</TableCell></TableRow>)}</TableBody></Table>
                                )}
                            </DashboardQueuePanel>

                            <DashboardQueuePanel title="Open disputes" description="Cases not yet resolved." href={links.disputes} locked={!sectionAccess.open_disputes}>
                                {!openDisputes?.length ? <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground">No open disputes.</p> : (
                                    <Table><TableHeader><TableRow><TableHead>Order</TableHead><TableHead>Status</TableHead><TableHead>Opened</TableHead></TableRow></TableHeader><TableBody>{openDisputes.map((row) => <TableRow key={row.id}><TableCell className="font-medium">{row.order_number ?? `#${row.id}`}</TableCell><TableCell><StatusBadge status={row.status} /></TableCell><TableCell className="text-muted-foreground">{fmtDate(row.opened_at)}</TableCell></TableRow>)}</TableBody></Table>
                                )}
                            </DashboardQueuePanel>

                            <DashboardQueuePanel title="Pending withdrawals" description="Requested or under review." href={links.withdrawals} locked={!sectionAccess.pending_withdrawals}>
                                {!pendingWithdrawals?.length ? <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground">No pending withdrawals.</p> : (
                                    <Table><TableHeader><TableRow><TableHead>Seller</TableHead><TableHead>Amount</TableHead><TableHead>Status</TableHead><TableHead>Requested</TableHead></TableRow></TableHeader><TableBody>{pendingWithdrawals.map((row) => <TableRow key={row.id}><TableCell className="max-w-[160px] truncate font-medium">{row.seller_display_name ?? '—'}</TableCell><TableCell className="tabular-nums">{fmtMoney(row.requested_amount, row.currency)}</TableCell><TableCell><StatusBadge status={row.status} /></TableCell><TableCell className="text-muted-foreground">{fmtDate(row.created_at)}</TableCell></TableRow>)}</TableBody></Table>
                                )}
                            </DashboardQueuePanel>

                            <DashboardQueuePanel title="Wallet top-ups" description="Manual wallet funding requests awaiting finance review." href={links.wallet_top_ups} locked={!sectionAccess.pending_wallet_top_ups}>
                                {!pendingWalletTopUps?.length ? <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground">No wallet top-ups pending review.</p> : (
                                    <Table><TableHeader><TableRow><TableHead>User</TableHead><TableHead>Method</TableHead><TableHead>Amount</TableHead><TableHead>Submitted</TableHead><TableHead className="text-right">Review</TableHead></TableRow></TableHeader><TableBody>{pendingWalletTopUps.map((row) => <TableRow key={row.id}><TableCell className="max-w-[160px] truncate font-medium">{row.user ?? '—'}</TableCell><TableCell><Badge variant="outline" className="font-normal">{row.method ?? '—'}</Badge></TableCell><TableCell className="tabular-nums">{row.amount ?? '—'}</TableCell><TableCell className="text-muted-foreground">{fmtDate(row.created_at)}</TableCell><TableCell className="text-right">{row.href ? <Button variant="outline" size="sm" asChild><Link href={row.href}>Open</Link></Button> : '—'}</TableCell></TableRow>)}</TableBody></Table>
                                )}
                            </DashboardQueuePanel>

                            <DashboardQueuePanel title="Seller verification queue" description="KYC submissions awaiting review." href={links.sellers} locked={!sectionAccess.seller_verification_queue}>
                                {!sellerVerificationQueue?.length ? <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground">Verification queue is clear.</p> : (
                                    <Table><TableHeader><TableRow><TableHead>Seller</TableHead><TableHead>KYC</TableHead><TableHead>Profile</TableHead><TableHead>Assignee</TableHead><TableHead>SLA</TableHead><TableHead>Submitted</TableHead><TableHead className="text-right">Workspace</TableHead></TableRow></TableHeader><TableBody>{sellerVerificationQueue.map((row) => <TableRow key={row.id}><TableCell className="max-w-[160px] truncate font-medium">{row.seller_display_name ?? '—'}</TableCell><TableCell><StatusBadge status={row.status} /></TableCell><TableCell><Badge variant="outline" className="font-normal">{row.seller_verification_status ?? '—'}</Badge></TableCell><TableCell className="max-w-[180px] truncate text-muted-foreground">{row.assigned_to_email ?? 'Unassigned'}</TableCell><TableCell><Badge variant={row.sla_state === 'breach' ? 'danger' : row.sla_state === 'warning' ? 'secondary' : 'outline'} className="font-normal">{row.sla_state === 'breach' ? 'Escalated' : row.sla_state === 'warning' ? 'Due soon' : 'On track'}</Badge></TableCell><TableCell className="text-muted-foreground">{fmtDate(row.submitted_at)}</TableCell><TableCell className="text-right">{row.workspace_url ? <Button variant="outline" size="sm" asChild><Link href={row.workspace_url}>Open</Link></Button> : '—'}</TableCell></TableRow>)}</TableBody></Table>
                                )}
                            </DashboardQueuePanel>

                            <DashboardQueuePanel title="Product moderation" description="Drafts and inactive listings surfaced for review." href={links.products} locked={!sectionAccess.product_moderation}>
                                {!productModeration?.length ? <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground">Nothing in the moderation queue.</p> : (
                                    <Table><TableHeader><TableRow><TableHead>Product</TableHead><TableHead>Seller</TableHead><TableHead>Status</TableHead><TableHead>Updated</TableHead></TableRow></TableHeader><TableBody>{productModeration.map((row) => <TableRow key={row.id}><TableCell className="max-w-[200px] truncate font-medium">{row.title ?? '—'}</TableCell><TableCell className="max-w-[140px] truncate text-muted-foreground">{row.seller_display_name ?? '—'}</TableCell><TableCell><StatusBadge status={row.status} /></TableCell><TableCell className="text-muted-foreground">{fmtDate(row.updated_at)}</TableCell></TableRow>)}</TableBody></Table>
                                )}
                            </DashboardQueuePanel>
                        </div>
                    </section>

                    <section>
                        <h2 className="mb-4 text-lg font-semibold tracking-tight text-foreground dark:text-slate-100">System alerts</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {(systemAlerts ?? []).map((a, i) => (
                                <Card key={i} className="panel-card">
                                    <CardHeader className="border-b border-border/70 pb-2 dark:border-slate-700/80">
                                        <div className="flex items-center gap-2">
                                            <Badge variant={alertVariant(a.severity)}>{a.severity}</Badge>
                                            <CardTitle className="text-base dark:text-slate-100">{a.title}</CardTitle>
                                        </div>
                                        <CardDescription className="text-sm leading-relaxed dark:text-slate-400">{a.detail}</CardDescription>
                                    </CardHeader>
                                    {a.href ? <CardContent className="pt-0"><a href={a.href} className="text-sm font-medium text-primary hover:underline">Open</a></CardContent> : null}
                                </Card>
                            ))}
                        </div>
                    </section>

                    <section>
                        <div className="mb-4 flex items-end justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold tracking-tight text-foreground dark:text-slate-100">Finance inbox</h2>
                                <p className="text-sm text-muted-foreground dark:text-slate-400">Funding requests and ledger-related actions in one place.</p>
                            </div>
                            <Button variant="outline" asChild>
                                <Link href={links.wallet_top_ups}>Open wallet top-ups</Link>
                            </Button>
                        </div>
                        <Card className="panel-card">
                            <CardHeader className="border-b border-border/70 pb-2 dark:border-slate-700/80">
                                <CardTitle className="text-base dark:text-slate-100">Wallet funding queue</CardTitle>
                                <CardDescription className="dark:text-slate-400">Manual wallet top-ups are reviewed here before any balance credit is posted.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!pendingWalletTopUps?.length ? (
                                    <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground">
                                        No wallet top-ups pending review.
                                    </p>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>User</TableHead>
                                                <TableHead>Method</TableHead>
                                                <TableHead>Amount</TableHead>
                                                <TableHead>Submitted</TableHead>
                                                <TableHead className="text-right">Review</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {pendingWalletTopUps.map((row) => (
                                                <TableRow key={row.id}>
                                                    <TableCell className="max-w-[220px] truncate font-medium">{row.user ?? '—'}</TableCell>
                                                    <TableCell><Badge variant="outline" className="font-normal">{row.method ?? '—'}</Badge></TableCell>
                                                    <TableCell className="tabular-nums">{row.amount ?? '—'}</TableCell>
                                                    <TableCell className="text-muted-foreground">{fmtDate(row.created_at)}</TableCell>
                                                    <TableCell className="text-right">{row.href ? <Button variant="outline" size="sm" asChild><Link href={row.href}>Open</Link></Button> : '—'}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                </TabsContent>

                <TabsContent value="layout" className="mt-6">
                    <Card className="panel-card">
                        <CardHeader>
                            <CardTitle className="text-base dark:text-slate-100">Layout notes</CardTitle>
                            <CardDescription className="dark:text-slate-400">Stats use one aggregate query; queue tables use bounded selects with constrained eager loads. Financial totals sum raw decimals across currencies.</CardDescription>
                        </CardHeader>
                    </Card>
                </TabsContent>
            </Tabs>
        </AdminLayout>
    );
}

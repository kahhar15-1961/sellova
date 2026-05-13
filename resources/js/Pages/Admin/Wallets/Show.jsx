import { Head, Link } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatMoney } from '@/lib/utils';

export default function WalletShow({ header, wallet, entries, holds, snapshots, list_href, export_url }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader
                title={header.title}
                description={header.description}
                breadcrumbs={header.breadcrumbs}
                actions={
                    <Button asChild variant="outline" size="sm">
                        <a href={export_url}>Export ledger CSV</a>
                    </Button>
                }
            />
            <div className="mb-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href={list_href}>← Wallets</Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Wallet summary</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <p>User: {wallet.user_email ?? '—'}</p>
                    <p>Type: {wallet.wallet_type}</p>
                    <p>Currency: {wallet.currency ?? '—'}</p>
                    <p>
                        Status: <StatusBadge status={wallet.status} />
                    </p>
                    <p>Version: {wallet.version}</p>
                    <p>Created: {wallet.created_at}</p>
                </CardContent>
            </Card>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Recent ledger entries</CardTitle>
                </CardHeader>
                <CardContent>
                    {!entries?.length ? (
                        <p className="text-sm text-muted-foreground">No ledger entries.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>ID</TableHead>
                                    <TableHead>Side</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Balance after</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>At</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {entries.map((e) => (
                                    <TableRow key={e.id}>
                                        <TableCell>{e.id}</TableCell>
                                        <TableCell>{e.side}</TableCell>
                                        <TableCell>{e.type}</TableCell>
                                        <TableCell>{formatMoney(e.amount, wallet.currency, { currencyDisplay: 'code' })}</TableCell>
                                        <TableCell>{formatMoney(e.running_balance_after, wallet.currency, { currencyDisplay: 'code' })}</TableCell>
                                        <TableCell>{e.reference}</TableCell>
                                        <TableCell className="text-muted-foreground">{e.occurred_at ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Recent holds</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {!holds?.length ? (
                            <p className="text-sm text-muted-foreground">No holds.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>ID</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Amount</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {holds.map((h) => (
                                        <TableRow key={h.id}>
                                            <TableCell>{h.id}</TableCell>
                                            <TableCell><StatusBadge status={h.status} /></TableCell>
                                            <TableCell>{h.type}</TableCell>
                                            <TableCell>{formatMoney(h.amount, h.currency, { currencyDisplay: 'code' })}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Balance snapshots</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {!snapshots?.length ? (
                            <p className="text-sm text-muted-foreground">No snapshots.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>As of</TableHead>
                                        <TableHead>Available</TableHead>
                                        <TableHead>Held</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {snapshots.map((s, i) => (
                                        <TableRow key={i}>
                                            <TableCell className="text-muted-foreground">{s.as_of ?? '—'}</TableCell>
                                            <TableCell>{formatMoney(s.available_balance, wallet.currency, { currencyDisplay: 'code' })}</TableCell>
                                            <TableCell>{formatMoney(s.held_balance, wallet.currency, { currencyDisplay: 'code' })}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch {
        return '—';
    }
}

/**
 * @param {{
 *   header: { title: string, description?: string, breadcrumbs?: { label: string, href?: string }[] },
 *   tab: string,
 *   q: string,
 *   rows: object[],
 *   pagination: { current_page: number, last_page: number, per_page: number, total: number, from?: number|null, to?: number|null },
 * }} props
 */
export default function SellersIndex({ header, tab, q, rows, pagination }) {
    const flash = usePage().props.flash ?? {};
    const qRef = useRef(null);

    const applyFilters = (next = {}) => {
        router.get(
            '/admin/sellers',
            {
                tab: next.tab ?? tab,
                q: next.q !== undefined ? next.q : q,
                page: next.page ?? 1,
            },
            { preserveState: true, replace: true },
        );
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

            <Card className="mb-6 border-border/80 shadow-sm">
                <CardHeader className="pb-4">
                    <CardTitle className="text-base">Filters</CardTitle>
                    <CardDescription>Search by account email and switch lifecycle tabs.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="flex-1 space-y-2">
                        <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground" htmlFor="kyc-q">
                            Account email
                        </label>
                        <Input
                            ref={qRef}
                            id="kyc-q"
                            defaultValue={q}
                            placeholder="name@company.com"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    applyFilters({ q: e.currentTarget.value, page: 1 });
                                }
                            }}
                        />
                    </div>
                    <Button type="button" variant="secondary" onClick={() => applyFilters({ q: qRef.current?.value ?? '', page: 1 })}>
                        Search
                    </Button>
                </CardContent>
            </Card>

            <Tabs value={tab} onValueChange={(v) => applyFilters({ tab: v, page: 1 })} className="space-y-4">
                <TabsList className="grid w-full max-w-2xl grid-cols-5">
                    <TabsTrigger value="pending">Pending</TabsTrigger>
                    <TabsTrigger value="all">All</TabsTrigger>
                    <TabsTrigger value="approved">Approved</TabsTrigger>
                    <TabsTrigger value="rejected">Rejected</TabsTrigger>
                    <TabsTrigger value="expired">Expired</TabsTrigger>
                </TabsList>
            </Tabs>

            <Card className="mt-2 border-border/80 shadow-sm">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                    <div>
                        <CardTitle className="text-base">Verification cases</CardTitle>
                        <CardDescription>
                            Showing {pagination.from ?? 0}–{pagination.to ?? 0} of {pagination.total}
                        </CardDescription>
                    </div>
                </CardHeader>
                <CardContent className="pt-0">
                    {!rows?.length ? (
                        <p className="rounded-md border border-dashed border-border/80 bg-muted/20 px-4 py-12 text-center text-sm text-muted-foreground">
                            No cases match this view.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Case</TableHead>
                                    <TableHead>Seller</TableHead>
                                    <TableHead>Account</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead className="text-right">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-mono text-xs text-muted-foreground">#{row.id}</TableCell>
                                        <TableCell className="max-w-[160px] truncate font-medium">{row.seller_display_name ?? '—'}</TableCell>
                                        <TableCell className="max-w-[200px] truncate text-muted-foreground">{row.account_email ?? '—'}</TableCell>
                                        <TableCell>
                                            <StatusBadge status={row.status} />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{fmtDate(row.submitted_at)}</TableCell>
                                        <TableCell className="text-right">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={row.workspace_url}>Open workspace</Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    {pagination.last_page > 1 ? (
                        <div className="mt-4 flex justify-center gap-2">
                            {pagination.current_page > 1 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get('/admin/sellers', {
                                            tab,
                                            q,
                                            page: pagination.current_page - 1,
                                        })
                                    }
                                >
                                    Previous
                                </Button>
                            ) : null}
                            {pagination.current_page < pagination.last_page ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get('/admin/sellers', {
                                            tab,
                                            q,
                                            page: pagination.current_page + 1,
                                        })
                                    }
                                >
                                    Next
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}

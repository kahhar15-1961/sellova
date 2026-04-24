import { Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatCard } from '@/components/admin/StatCard';
import { DetailSection } from '@/components/admin/DetailSection';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

/**
 * @param {{ header: { title: string, description?: string, breadcrumbs?: { label: string, href?: string }[] }, stats: { key: string, label: string, value: string, hint?: string }[] }} props
 */
export default function Dashboard({ header, stats }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((s) => (
                    <StatCard key={s.key} label={s.label} value={s.value} hint={s.hint} />
                ))}
            </div>
            <div className="mt-10">
                <Tabs defaultValue="overview">
                    <TabsList>
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="ops">Operations</TabsTrigger>
                    </TabsList>
                    <TabsContent value="overview" className="mt-4">
                        <DetailSection title="Foundation status">
                            <p>
                                Admin shell is live: navigation, RBAC middleware, and Inertia props for{' '}
                                <code className="rounded bg-muted px-1 py-0.5 text-xs">filters</code>,{' '}
                                <code className="rounded bg-muted px-1 py-0.5 text-xs">pagination</code>,{' '}
                                <code className="rounded bg-muted px-1 py-0.5 text-xs">stats</code>, and{' '}
                                <code className="rounded bg-muted px-1 py-0.5 text-xs">can.*</code> are ready for module wiring.
                            </p>
                        </DetailSection>
                    </TabsContent>
                    <TabsContent value="ops" className="mt-4">
                        <DetailSection title="Next wiring points">
                            <ul className="list-inside list-disc space-y-1">
                                <li>Connect dashboard stats to read-only service queries.</li>
                                <li>Keep mutating flows behind ConfirmDialog + audit logging.</li>
                                <li>Use Gate::allows(permission) in controllers alongside route middleware.</li>
                            </ul>
                        </DetailSection>
                    </TabsContent>
                </Tabs>
            </div>
        </AdminLayout>
    );
}

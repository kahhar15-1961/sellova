import { Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { DetailSection } from '@/components/admin/DetailSection';
import { ActionPanel } from '@/components/admin/ActionPanel';
import { Button } from '@/components/ui/button';

export default function SettingsIndex({ header }) {
    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="grid gap-8 lg:grid-cols-[1fr_280px]">
                <div className="space-y-6">
                    <DetailSection title="Environment & feature flags">
                        <p>Configuration surfaces will map to audited admin actions and read-only previews first.</p>
                    </DetailSection>
                    <DetailSection title="Danger zone (planned)">
                        <p className="text-amber-900/90">
                            Destructive changes will require elevated permission, re-authentication, and audit entries.
                        </p>
                    </DetailSection>
                </div>
                <ActionPanel title="Shortcuts">
                    <Button type="button" variant="outline" size="sm" disabled>
                        Export settings
                    </Button>
                    <Button type="button" variant="outline" size="sm" disabled>
                        View change log
                    </Button>
                </ActionPanel>
            </div>
        </AdminLayout>
    );
}

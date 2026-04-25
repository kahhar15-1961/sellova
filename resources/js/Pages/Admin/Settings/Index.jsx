import { Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { DetailSection } from '@/components/admin/DetailSection';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function SettingsIndex({ header, environment }) {
    const e = environment || {};

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            <div className="grid gap-8 lg:grid-cols-[1fr_280px]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Runtime snapshot</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm sm:grid-cols-2">
                            <p>
                                <span className="text-muted-foreground">App</span>
                                <br />
                                <span className="font-medium">{e.app_name}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Environment</span>
                                <br />
                                <span className="font-medium">{e.app_env}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Debug</span>
                                <br />
                                <span className="font-medium">{e.app_debug ? 'on' : 'off'}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">URL</span>
                                <br />
                                <span className="break-all font-medium">{e.app_url}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">DB connection</span>
                                <br />
                                <span className="font-medium">{e.db_connection}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Cache</span>
                                <br />
                                <span className="font-medium">{e.cache_store}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Session</span>
                                <br />
                                <span className="font-medium">{e.session_driver}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Queue</span>
                                <br />
                                <span className="font-medium">{e.queue_connection}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">Mail</span>
                                <br />
                                <span className="font-medium">{e.mail_mailer}</span>
                            </p>
                        </CardContent>
                    </Card>
                    <DetailSection title="Operational note">
                        <p>
                            Secrets and provider keys stay in environment configuration and your secrets manager — nothing
                            sensitive is rendered here.
                        </p>
                    </DetailSection>
                </div>
            </div>
        </AdminLayout>
    );
}

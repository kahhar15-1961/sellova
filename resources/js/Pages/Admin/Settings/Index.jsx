import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { DetailSection } from '@/components/admin/DetailSection';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export default function SettingsIndex({
    header,
    environment,
    push_settings: pushSettings,
    push_saved: pushSaved,
    push_tested: pushTested,
    push_test_recipient: pushTestRecipient,
    timeout_settings: timeoutSettings,
    timeout_saved: timeoutSaved,
    withdrawal_settings: withdrawalSettings,
    withdrawal_saved: withdrawalSaved,
}) {
    const e = environment || {};
    const p = pushSettings || {};
    const form = useForm({
        enabled: Boolean(p.enabled),
        provider: p.provider || 'fcm',
        fcm_project_id: p.fcm_project_id || '',
        fcm_client_email: p.fcm_client_email || '',
        fcm_private_key: p.fcm_private_key || '',
        android_channel_id: p.android_channel_id || 'sellova-default',
        android_channel_name: p.android_channel_name || 'Sellova',
        android_channel_description: p.android_channel_description || 'Order, wallet, and support alerts.',
    });
    const testForm = useForm({
        recipient_email: '',
        title: 'Sellova push test',
        body: 'This is a test push from admin settings.',
    });
    const t = timeoutSettings || {};
    const w = withdrawalSettings || {};
    const withdrawalForm = useForm({
        minimum_withdrawal_amount: w.minimum_withdrawal_amount || '500.0000',
        currency: w.currency || 'BDT',
    });
    const timeoutForm = useForm({
        unpaid_order_expiration_minutes: t.unpaid_order_expiration_minutes || 30,
        seller_fulfillment_deadline_hours: t.seller_fulfillment_deadline_hours || 24,
        buyer_review_deadline_hours: t.buyer_review_deadline_hours || 72,
        buyer_review_reminder_1_hours: t.buyer_review_reminder_1_hours || 24,
        buyer_review_reminder_2_hours: t.buyer_review_reminder_2_hours || 48,
        seller_min_fulfillment_hours: t.seller_min_fulfillment_hours || 1,
        seller_max_fulfillment_hours: t.seller_max_fulfillment_hours || 168,
        buyer_min_review_hours: t.buyer_min_review_hours || 1,
        buyer_max_review_hours: t.buyer_max_review_hours || 168,
        auto_escalation_after_review_expiry: Boolean(t.auto_escalation_after_review_expiry),
        auto_cancel_unpaid_orders: Boolean(t.auto_cancel_unpaid_orders),
        auto_release_after_buyer_timeout: Boolean(t.auto_release_after_buyer_timeout),
        auto_create_dispute_on_timeout: Boolean(t.auto_create_dispute_on_timeout),
        dispute_review_queue_enabled: Boolean(t.dispute_review_queue_enabled),
        unpaid_order_warning_minutes: t.unpaid_order_warning_minutes || 10,
        seller_fulfillment_warning_hours: t.seller_fulfillment_warning_hours || 2,
        escalation_warning_minutes: t.escalation_warning_minutes || 60,
    });

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
                    <Card>
                        <CardHeader>
                            <CardTitle>Escrow timeouts</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-3">
                                {[
                                    ['unpaid_order_expiration_minutes', 'Unpaid expiry minutes'],
                                    ['unpaid_order_warning_minutes', 'Unpaid warning minutes'],
                                    ['seller_fulfillment_deadline_hours', 'Seller deadline hours'],
                                    ['seller_fulfillment_warning_hours', 'Seller warning hours'],
                                    ['buyer_review_deadline_hours', 'Buyer review hours'],
                                    ['buyer_review_reminder_1_hours', 'Reminder 1 hours'],
                                    ['buyer_review_reminder_2_hours', 'Reminder 2 hours'],
                                    ['escalation_warning_minutes', 'Escalation warning minutes'],
                                    ['seller_min_fulfillment_hours', 'Seller min hours'],
                                    ['seller_max_fulfillment_hours', 'Seller max hours'],
                                    ['buyer_min_review_hours', 'Buyer min hours'],
                                    ['buyer_max_review_hours', 'Buyer max hours'],
                                ].map(([key, label]) => (
                                    <div className="space-y-2" key={key}>
                                        <Label htmlFor={key}>{label}</Label>
                                        <Input
                                            id={key}
                                            type="number"
                                            min="1"
                                            value={timeoutForm.data[key]}
                                            onChange={(event) => timeoutForm.setData(key, event.target.value)}
                                        />
                                    </div>
                                ))}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {[
                                    ['auto_cancel_unpaid_orders', 'Auto-cancel unpaid orders'],
                                    ['auto_escalation_after_review_expiry', 'Escalate review expiry'],
                                    ['auto_release_after_buyer_timeout', 'Auto-release after buyer timeout'],
                                    ['auto_create_dispute_on_timeout', 'Create dispute on timeout'],
                                    ['dispute_review_queue_enabled', 'Dispute review queue'],
                                ].map(([key, label]) => (
                                    <div className="flex items-center justify-between rounded-lg border border-border/80 px-4 py-3" key={key}>
                                        <p className="font-medium">{label}</p>
                                        <Switch checked={timeoutForm.data[key]} onCheckedChange={(checked) => timeoutForm.setData(key, checked)} />
                                    </div>
                                ))}
                            </div>
                            <div className="flex items-center gap-3">
                                <Button
                                    onClick={() => timeoutForm.post('/admin/settings/escrow-timeouts', { preserveScroll: true })}
                                    disabled={timeoutForm.processing}
                                >
                                    {timeoutForm.processing ? 'Saving...' : 'Save timeout settings'}
                                </Button>
                                {timeoutSaved ? <span className="text-sm text-green-600">Saved</span> : null}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Withdrawal controls</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Set the minimum seller payout amount. Seller apps use this value to disable withdrawal
                                requests before review, and the API enforces it again server-side.
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="minimum_withdrawal_amount">Minimum withdrawal amount</Label>
                                    <Input
                                        id="minimum_withdrawal_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={withdrawalForm.data.minimum_withdrawal_amount}
                                        onChange={(event) => withdrawalForm.setData('minimum_withdrawal_amount', event.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="withdrawal_currency">Currency</Label>
                                    <Input
                                        id="withdrawal_currency"
                                        value={withdrawalForm.data.currency}
                                        maxLength={3}
                                        onChange={(event) => withdrawalForm.setData('currency', event.target.value.toUpperCase())}
                                    />
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Button
                                    onClick={() => withdrawalForm.post('/admin/settings/withdrawals', { preserveScroll: true })}
                                    disabled={withdrawalForm.processing}
                                >
                                    {withdrawalForm.processing ? 'Saving...' : 'Save withdrawal settings'}
                                </Button>
                                {withdrawalSaved ? <span className="text-sm text-green-600">Saved</span> : null}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Push notifications</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Configure Firebase Cloud Messaging here. iOS uses FCM to reach APNs, so this becomes the
                                single control point for live push delivery.
                            </p>
                            <div className="flex items-center justify-between rounded-lg border border-border/80 px-4 py-3">
                                <div>
                                    <p className="font-medium">Enabled</p>
                                    <p className="text-xs text-muted-foreground">Turns live push delivery on or off.</p>
                                </div>
                                <Switch checked={form.data.enabled} onCheckedChange={(checked) => form.setData('enabled', checked)} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="provider">Provider</Label>
                                    <Input
                                        id="provider"
                                        value={form.data.provider}
                                        onChange={(e) => form.setData('provider', e.target.value)}
                                        placeholder="fcm"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="project">Firebase project ID</Label>
                                    <Input
                                        id="project"
                                        value={form.data.fcm_project_id}
                                        onChange={(e) => form.setData('fcm_project_id', e.target.value)}
                                        placeholder="sellova-prod"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="client_email">Service account email</Label>
                                    <Input
                                        id="client_email"
                                        value={form.data.fcm_client_email}
                                        onChange={(e) => form.setData('fcm_client_email', e.target.value)}
                                        placeholder="firebase-adminsdk@project.iam.gserviceaccount.com"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="channel_id">Android channel ID</Label>
                                    <Input
                                        id="channel_id"
                                        value={form.data.android_channel_id}
                                        onChange={(e) => form.setData('android_channel_id', e.target.value)}
                                        placeholder="sellova-default"
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="private_key">Service account private key</Label>
                                    <textarea
                                        id="private_key"
                                        className="min-h-40 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none transition focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                                        value={form.data.fcm_private_key}
                                        onChange={(e) => form.setData('fcm_private_key', e.target.value)}
                                        placeholder="-----BEGIN PRIVATE KEY-----"
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="channel_name">Channel name</Label>
                                    <Input
                                        id="channel_name"
                                        value={form.data.android_channel_name}
                                        onChange={(e) => form.setData('android_channel_name', e.target.value)}
                                        placeholder="Sellova"
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="channel_desc">Channel description</Label>
                                    <Input
                                        id="channel_desc"
                                        value={form.data.android_channel_description}
                                        onChange={(e) => form.setData('android_channel_description', e.target.value)}
                                        placeholder="Order, wallet, and support alerts."
                                    />
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Button
                                    onClick={() =>
                                        form.post('/admin/settings/push-notifications', {
                                            preserveScroll: true,
                                        })
                                    }
                                    disabled={form.processing}
                                >
                                    {form.processing ? 'Saving...' : 'Save push settings'}
                                </Button>
                                {pushSaved ? (
                                    <span className="text-sm text-green-600">Saved</span>
                                ) : null}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Test push</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Send a live test notification to a user account to verify Firebase, device registration,
                                and websocket delivery.
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="recipient_email">Recipient email</Label>
                                    <Input
                                        id="recipient_email"
                                        value={testForm.data.recipient_email}
                                        onChange={(e) => testForm.setData('recipient_email', e.target.value)}
                                        placeholder={pushTestRecipient || 'admin@example.test'}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="push_title">Title</Label>
                                    <Input
                                        id="push_title"
                                        value={testForm.data.title}
                                        onChange={(e) => testForm.setData('title', e.target.value)}
                                        placeholder="Sellova push test"
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="push_body">Message</Label>
                                    <textarea
                                        id="push_body"
                                        className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none transition focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                                        value={testForm.data.body}
                                        onChange={(e) => testForm.setData('body', e.target.value)}
                                        placeholder="This is a test push from admin settings."
                                    />
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Button
                                    onClick={() =>
                                        testForm.post('/admin/settings/push-notifications/test', {
                                            preserveScroll: true,
                                        })
                                    }
                                    disabled={testForm.processing}
                                >
                                    {testForm.processing ? 'Sending...' : 'Send test push'}
                                </Button>
                                {pushTested ? (
                                    <span className="text-sm text-green-600">
                                        Sent to {pushTestRecipient || 'recipient'}
                                    </span>
                                ) : null}
                            </div>
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

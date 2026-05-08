import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

function TonePill({ tone, children }) {
    const classes = {
        pass: 'bg-emerald-100 text-emerald-700',
        warn: 'bg-amber-100 text-amber-700',
        fail: 'bg-rose-100 text-rose-700',
        muted: 'bg-slate-100 text-slate-600',
    };
    return (
        <span className={`rounded-full px-2 py-1 text-xs font-medium ${classes[tone] ?? classes.muted}`}>
            {children}
        </span>
    );
}

function toneForIntegrationState(state) {
    switch (state) {
        case 'configured':
            return 'pass';
        case 'manual':
            return 'warn';
        case 'disabled':
        default:
            return 'muted';
    }
}

function ToggleButton({ url, enabled }) {
    return (
        <Button
            variant={enabled ? 'secondary' : 'outline'}
            size="sm"
            onClick={() => router.post(url, {}, { preserveScroll: true })}
            className="rounded-full"
        >
            {enabled ? 'Disable' : 'Enable'}
        </Button>
    );
}

function TestButton({ url }) {
    return (
        <Button
            variant="outline"
            size="sm"
            onClick={() => router.post(url, {}, { preserveScroll: true })}
            className="rounded-full"
        >
            Test connection
        </Button>
    );
}

function GatewayForm({ gateway, storeUrl, updateUrlTemplate, methodOptions, driverOptions }) {
    const form = useForm({
        code: gateway?.code ?? '',
        name: gateway?.name ?? '',
        method: gateway?.method ?? 'card',
        driver: gateway?.driver ?? 'manual',
        is_enabled: gateway?.is_enabled ?? false,
        is_default: gateway?.is_default ?? false,
        priority: gateway?.priority ?? 0,
        supported_methods: gateway?.supported_methods ?? [gateway?.method ?? 'card'],
        checkout_url: gateway?.checkout_url ?? '',
        callback_url: gateway?.callback_url ?? '',
        webhook_url: gateway?.webhook_url ?? '',
        public_key: gateway?.public_key ?? '',
        merchant_id: gateway?.merchant_id ?? '',
        description: gateway?.description ?? '',
        credentials: JSON.stringify(gateway?.credentials ?? {}, null, 2),
        extra_json: JSON.stringify(gateway?.extra_json ?? {}, null, 2),
        wallet_manual_top_up_enabled: gateway?.wallet_manual_top_up_enabled ?? false,
        wallet_manual_top_up_label:
            gateway?.wallet_manual_top_up_label ?? 'Manual review',
    });

    const action = gateway ? updateUrlTemplate.replace('__ID__', String(gateway.id)) : storeUrl;

    const toggleMethod = (value) => {
        form.setData(
            'supported_methods',
            form.data.supported_methods.includes(value)
                ? form.data.supported_methods.filter((method) => method !== value)
                : [...form.data.supported_methods, value]
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>{gateway ? 'Edit gateway' : 'New gateway'}</CardTitle>
                <CardDescription>Store provider credentials, routing mode, and supported payment methods.</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-4"
                        onSubmit={(e) => {
                        e.preventDefault();
                        form.post(action, {
                            preserveScroll: true,
                            onSuccess: () => form.reset(
                                'credentials',
                                'extra_json',
                                'wallet_manual_top_up_enabled',
                                'wallet_manual_top_up_label',
                            ),
                        });
                    }}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Code">
                            <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="sslcommerz" />
                        </Field>
                        <Field label="Display name">
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="SSLCommerz" />
                        </Field>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Primary method">
                            <select
                                value={form.data.method}
                                onChange={(e) => form.setData('method', e.target.value)}
                                className="h-10 w-full rounded-md border px-3 text-sm"
                            >
                                {methodOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Driver">
                            <select
                                value={form.data.driver}
                                onChange={(e) => form.setData('driver', e.target.value)}
                                className="h-10 w-full rounded-md border px-3 text-sm"
                            >
                                {driverOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Priority">
                            <Input
                                type="number"
                                min="0"
                                value={form.data.priority}
                                onChange={(e) => form.setData('priority', Number(e.target.value))}
                            />
                        </Field>
                    </div>

                    <div className="grid gap-3 md:grid-cols-4">
                        {methodOptions.map((option) => (
                            <label key={option.value} className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.supported_methods.includes(option.value)}
                                    onChange={() => toggleMethod(option.value)}
                                />
                                {option.label}
                            </label>
                        ))}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Checkout URL">
                            <Input value={form.data.checkout_url} onChange={(e) => form.setData('checkout_url', e.target.value)} placeholder="https://..." />
                        </Field>
                        <Field label="Callback URL">
                            <Input value={form.data.callback_url} onChange={(e) => form.setData('callback_url', e.target.value)} placeholder="https://..." />
                        </Field>
                        <Field label="Webhook URL">
                            <Input value={form.data.webhook_url} onChange={(e) => form.setData('webhook_url', e.target.value)} placeholder="https://..." />
                        </Field>
                        <Field label="Public key">
                            <Input value={form.data.public_key} onChange={(e) => form.setData('public_key', e.target.value)} placeholder="publishable key / app key" />
                        </Field>
                        <Field label="Merchant ID">
                            <Input value={form.data.merchant_id} onChange={(e) => form.setData('merchant_id', e.target.value)} placeholder="merchant or store ID" />
                        </Field>
                        <Field label="Description">
                            <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Short display note" />
                        </Field>
                    </div>

                    <Field label="Credentials JSON">
                        <Textarea
                            rows={5}
                            value={form.data.credentials}
                            onChange={(e) => form.setData('credentials', e.target.value)}
                            placeholder='{"secret_key":"...","webhook_secret":"..."}'
                        />
                    </Field>
                    <Field label="Extra JSON">
                        <Textarea
                            rows={4}
                            value={form.data.extra_json}
                            onChange={(e) => form.setData('extra_json', e.target.value)}
                            placeholder='{"mode":"sandbox"}'
                        />
                    </Field>

                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.wallet_manual_top_up_enabled}
                                onChange={(e) =>
                                    form.setData('wallet_manual_top_up_enabled', e.target.checked)
                                }
                            />
                            Allow manual wallet top-up requests
                        </label>
                        <Field label="Manual label">
                            <Input
                                value={form.data.wallet_manual_top_up_label}
                                onChange={(e) => form.setData('wallet_manual_top_up_label', e.target.value)}
                                placeholder="Manual review"
                            />
                        </Field>
                    </div>

                    <div className="grid gap-3 md:grid-cols-3">
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.is_enabled} onChange={(e) => form.setData('is_enabled', e.target.checked)} />
                            Enabled
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.is_default} onChange={(e) => form.setData('is_default', e.target.checked)} />
                            Default route
                        </label>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Credentials are encrypted at rest. Use Test connection to validate the setup before enabling it.
                    </p>

                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {gateway ? 'Update gateway' : 'Create gateway'}
                        </Button>
                        {gateway ? (
                            <Button variant="outline" asChild>
                                <Link href="/admin/settings/payment-gateways">Cancel</Link>
                            </Button>
                        ) : null}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function Field({ label, children }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function GatewayTestResult({ result }) {
    if (!result) {
        return null;
    }

    return (
        <Card className="mb-6 border-slate-200 bg-slate-50/80">
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    Connection test
                    <TonePill tone={result.status}>{result.status}</TonePill>
                </CardTitle>
                <CardDescription>{result.summary}</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {(result.checks || []).map((check) => (
                    <div key={check.label} className="flex items-start justify-between gap-4 rounded-xl border bg-white px-4 py-3">
                        <div>
                            <div className="font-medium">{check.label}</div>
                            <div className="text-xs text-muted-foreground">{check.message}</div>
                        </div>
                        <TonePill tone={check.status}>{check.status}</TonePill>
                    </div>
                ))}
                {result.issues?.length ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Notes: {result.issues.join(' ')}
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

export default function PaymentGatewaysIndex({
    header,
    gateways,
    editing_gateway,
    store_url,
    update_url_template,
    toggle_url_template,
    test_url_template,
    method_options,
    driver_options,
}) {
    const page = usePage();
    const flash = page.props.flash || {};
    const testResult = flash.gateway_test_result;

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />
            {flash.success ? <p className="mb-4 text-sm text-emerald-700">{flash.success}</p> : null}
            <GatewayTestResult result={testResult} />

            <Card className="mb-6 border-slate-200 bg-slate-50/70">
                <CardHeader>
                    <CardTitle>Integration status</CardTitle>
                    <CardDescription>
                        Gateway configuration is available in admin, but these providers are not live PSP SDK integrations yet.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-3">
                    <div className="rounded-xl border bg-white px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-muted-foreground">SSLCommerz</div>
                        <div className="mt-1 font-medium">Configured, not live SDK</div>
                    </div>
                    <div className="rounded-xl border bg-white px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-muted-foreground">bKash</div>
                        <div className="mt-1 font-medium">Configured, manual or routed</div>
                    </div>
                    <div className="rounded-xl border bg-white px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-muted-foreground">Nagad</div>
                        <div className="mt-1 font-medium">Configured, manual or routed</div>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Configured gateways</CardTitle>
                        <CardDescription>
                            Enabled providers are exposed to checkout; each row shows whether the gateway is manual capture or a configured redirect/API route.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-4">Name</th>
                                        <th className="py-2 pr-4">Methods</th>
                                        <th className="py-2 pr-4">Mode</th>
                                        <th className="py-2 pr-4">Config</th>
                                        <th className="py-2 pr-4">Integration</th>
                                        <th className="py-2 pr-4">Priority</th>
                                        <th className="py-2 pr-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {gateways.map((gateway) => (
                                        <tr key={gateway.id} className="border-b last:border-0">
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">{gateway.name}</div>
                                                <div className="text-xs text-muted-foreground">{gateway.code}</div>
                                            </td>
                                            <td className="py-3 pr-4">{gateway.supported_methods?.join(', ') || gateway.method}</td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-col gap-1">
                                                    <TonePill tone={gateway.driver === 'manual' ? 'warn' : 'pass'}>
                                                        {gateway.driver_label || gateway.driver}
                                                    </TonePill>
                                                    <span className="text-xs text-muted-foreground">
                                                        {gateway.driver === 'manual' ? 'Manual review or capture' : 'Configured routing only'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-1">
                                                    <TonePill tone={gateway.credentials_present ? 'muted' : 'warn'}>
                                                        {gateway.credentials_present ? `${gateway.credential_keys?.length ?? 0} keys` : 'No secrets'}
                                                    </TonePill>
                                                    {gateway.wallet_manual_top_up_enabled ? (
                                                        <TonePill tone="pass">
                                                            {gateway.wallet_manual_top_up_label || 'Manual top-up'}
                                                        </TonePill>
                                                    ) : null}
                                                    {gateway.checkout_url ? <TonePill tone="muted">Checkout URL</TonePill> : null}
                                                    {gateway.callback_url ? <TonePill tone="muted">Callback</TonePill> : null}
                                                    {gateway.webhook_url ? <TonePill tone="muted">Webhook</TonePill> : null}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-col gap-1">
                                                    <TonePill tone={toneForIntegrationState(gateway.integration_state)}>
                                                        {gateway.integration_state === 'configured'
                                                            ? 'Configured'
                                                            : gateway.integration_state === 'manual'
                                                                ? 'Manual'
                                                                : 'Disabled'}
                                                    </TonePill>
                                                    <span className="text-xs text-muted-foreground">
                                                        {gateway.integration_state === 'configured'
                                                            ? 'Ready in admin, not live PSP SDK'
                                                            : gateway.integration_state === 'manual'
                                                                ? 'Uses manual review flow'
                                                                : 'Not exposed to checkout'}
                                                    </span>
                                                </div>
                                                {gateway.is_default ? <span className="ml-2"><TonePill tone="warn">Default</TonePill></span> : null}
                                            </td>
                                            <td className="py-3 pr-4">{gateway.priority}</td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/admin/settings/payment-gateways?edit=${gateway.id}`}>Edit</Link>
                                                    </Button>
                                                    <TestButton url={test_url_template.replace('__ID__', String(gateway.id))} />
                                                    <ToggleButton url={toggle_url_template.replace('__ID__', String(gateway.id))} enabled={gateway.is_enabled} />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <GatewayForm
                    gateway={editing_gateway}
                    storeUrl={store_url}
                    updateUrlTemplate={update_url_template}
                    methodOptions={method_options}
                    driverOptions={driver_options}
                />
            </div>
        </AdminLayout>
    );
}

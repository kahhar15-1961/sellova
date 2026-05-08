import { Form, Head } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatCard } from '@/components/admin/StatCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

function permissionLabel(code) {
    return code
        .replace(/^admin\./, '')
        .replace(/\./g, ' · ')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (m) => m.toUpperCase());
}

export default function AccessControlIndex({
    header,
    roles = [],
    permission_groups: permissionGroups = {},
    summary = {},
    store_url,
    update_url_template,
}) {
    const entries = Object.entries(permissionGroups);

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                <StatCard label="Roles" value={String(summary.roles ?? 0)} />
                <StatCard label="Permissions" value={String(summary.permissions ?? 0)} />
                <StatCard label="Managed roles" value={String(roles.length)} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Create role</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form action={store_url} method="post" className="space-y-3">
                            <input
                                name="code"
                                className="h-10 w-full rounded-md border px-3 text-sm"
                                placeholder="promo_manager"
                            />
                            <input
                                name="name"
                                className="h-10 w-full rounded-md border px-3 text-sm"
                                placeholder="Promotion Manager"
                            />
                            <p className="text-xs text-muted-foreground">
                                Use lowercase snake_case for the code. Permissions are assigned on the right.
                            </p>
                            <Button type="submit" className="w-full">Create role</Button>
                        </Form>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    {roles.length === 0 ? (
                        <Card>
                            <CardContent className="p-8 text-sm text-muted-foreground">
                                No roles found.
                            </CardContent>
                        </Card>
                    ) : (
                        roles.map((role) => {
                            const selected = new Set(role.permission_ids || []);
                            return (
                                <Card key={role.id} className="border-border/70">
                                    <CardHeader className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <CardTitle className="text-lg">{role.name}</CardTitle>
                                            <Badge variant="secondary">{role.code}</Badge>
                                            <Badge variant="outline">{role.user_count ?? 0} users</Badge>
                                            <Badge variant="outline">{(role.permission_ids || []).length} permissions</Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <Form action={update_url_template.replace('__ID__', role.id)} method="post" className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-sm font-medium text-foreground">Role name</label>
                                                <input
                                                    name="name"
                                                    defaultValue={role.name}
                                                    className="h-10 w-full rounded-md border px-3 text-sm"
                                                />
                                            </div>

                                            <div className="space-y-4">
                                                {entries.map(([groupName, permissions]) => (
                                                    <div key={groupName} className="rounded-lg border border-border/70 bg-muted/20 p-4">
                                                        <div className="mb-3 flex items-center justify-between gap-3">
                                                            <p className="text-sm font-semibold text-foreground">{groupName}</p>
                                                            <p className="text-xs text-muted-foreground">{permissions.length} permissions</p>
                                                        </div>
                                                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                                            {permissions.map((permission) => {
                                                                const checked = selected.has(permission.id);
                                                                return (
                                                                    <label
                                                                        key={permission.id}
                                                                        className="flex items-start gap-3 rounded-md border border-border/60 bg-background px-3 py-2 text-sm"
                                                                    >
                                                                        <input
                                                                            type="checkbox"
                                                                            name="permissions[]"
                                                                            value={permission.id}
                                                                            defaultChecked={checked}
                                                                            className="mt-1"
                                                                        />
                                                                        <span className="min-w-0">
                                                                            <span className="block font-medium text-foreground">{permissionLabel(permission.code)}</span>
                                                                            <span className="block text-xs text-muted-foreground">{permission.code}</span>
                                                                        </span>
                                                                    </label>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button type="submit">Save role permissions</Button>
                                            </div>
                                        </Form>
                                    </CardContent>
                                </Card>
                            );
                        })
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function Login() {
    const form = useForm({
        email: '',
        password: '',
    });

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-100 via-white to-slate-100 px-4">
            <Head title="Sign in" />
            <div className="mb-8 text-center">
                <p className="text-sm font-semibold uppercase tracking-[0.2em] text-muted-foreground">Sellova</p>
                <h1 className="mt-1 text-2xl font-semibold tracking-tight text-foreground">Staff console</h1>
            </div>
            <Card className="w-full max-w-md shadow-card">
                <CardHeader>
                    <CardTitle>Sign in</CardTitle>
                    <CardDescription>Use your staff account. Access is enforced by roles and permissions.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/admin/login');
                        }}
                    >
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-foreground" htmlFor="email">
                                Email
                            </label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                required
                            />
                            {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-foreground" htmlFor="password">
                                Password
                            </label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                required
                            />
                            {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
                        </div>
                        <Button type="submit" className="w-full" disabled={form.processing}>
                            {form.processing ? 'Signing in…' : 'Continue'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

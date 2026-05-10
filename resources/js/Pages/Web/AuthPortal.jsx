import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowRight, BadgeCheck, BriefcaseBusiness, CheckCircle2, Eye, EyeOff, LockKeyhole, Mail, ShieldCheck, ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

function Field({ label, id, error, children, action }) {
    return (
        <div>
            <div className="mb-2 flex items-center justify-between gap-3">
                <label htmlFor={id} className="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">{label}</label>
                {action}
            </div>
            {children}
            {error ? <p className="mt-2 text-xs font-semibold text-rose-600">{error}</p> : null}
        </div>
    );
}

function PasswordInput({ id, value, onChange, autoComplete, required = true }) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input
                id={id}
                type={visible ? 'text' : 'password'}
                value={value}
                onChange={onChange}
                className="h-12 bg-slate-50 pr-12 font-semibold"
                autoComplete={autoComplete}
                required={required}
            />
            <button
                type="button"
                onClick={() => setVisible((current) => !current)}
                className="absolute right-3 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-white hover:text-slate-900"
                aria-label={visible ? 'Hide password' : 'Show password'}
                aria-pressed={visible}
            >
                {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
        </div>
    );
}

export default function AuthPortal({ mode = 'login', panel = 'buyer' }) {
    const isRegister = mode === 'register';
    const isForgot = mode === 'forgot';
    const { flash } = usePage().props;
    const form = useForm({
        panel,
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        store_name: '',
        legal_name: '',
        country_code: 'BD',
        currency: 'BDT',
    });
    const activePanel = form.data.panel;
    const isSeller = activePanel === 'seller';
    const submit = (event) => {
        event.preventDefault();
        form.post(isForgot ? '/forgot-password' : (isRegister ? '/register' : '/login'), {
            preserveScroll: true,
        });
    };
    const title = isForgot ? 'Recover Access' : (isRegister ? 'Create Account' : 'Welcome Back');
    const subtitle = isForgot
        ? 'Enter your account email and we will start a secure recovery request.'
        : (isRegister ? 'Create one secure account for marketplace buying and seller operations.' : 'Sign in to access your secure vault, escrows, and workspace.');
    const submitLabel = isForgot
        ? 'Send Recovery Request'
        : (isRegister ? (isSeller ? 'Create Seller Workspace' : 'Create Buyer Account') : 'Sign In Securely');

    return (
        <main className="min-h-screen bg-[radial-gradient(circle_at_top_right,#e8edff,transparent_34%),linear-gradient(135deg,#f8fafc,#ffffff_45%,#f4f7fb)] px-4 py-8 text-slate-950">
            <Head title={isForgot ? 'Recover access' : (isRegister ? 'Create account' : 'Sign in')} />
            <div className="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl items-center gap-8 lg:grid-cols-[1fr_440px]">
                <section className="hidden lg:block">
                    <Link href="/" className="inline-flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-[0_20px_42px_-26px_rgba(79,70,229,0.9)]">
                            <ShoppingBag className="size-6" />
                        </span>
                        <span className="text-2xl font-black tracking-tight">Sellova</span>
                    </Link>
                    <h1 className="mt-12 max-w-2xl text-5xl font-black tracking-tight text-slate-950">
                        One secure operating system for buyers, sellers, carts, escrows, and payouts.
                    </h1>
                    <p className="mt-5 max-w-xl text-base font-medium leading-7 text-slate-600">
                        Sign in once, then move between protected buyer workflows and seller operations with live marketplace data.
                    </p>
                    <div className="mt-9 grid max-w-2xl gap-3 sm:grid-cols-3">
                        {[
                            [ShieldCheck, 'Escrow protected', 'Checkout and release controls'],
                            [BriefcaseBusiness, 'Seller command', 'Inventory, orders, payouts'],
                            [BadgeCheck, 'Verified signals', 'Profiles, reviews, risk state'],
                        ].map(([Icon, title, body]) => (
                            <div key={title} className="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                <Icon className="size-5 text-indigo-600" />
                                <p className="mt-4 text-sm font-black">{title}</p>
                                <p className="mt-1 text-xs font-medium leading-5 text-slate-500">{body}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_28px_80px_-52px_rgba(15,23,42,0.75)] sm:p-8">
                    <div className="text-center">
                        <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-[0_18px_38px_-24px_rgba(15,23,42,0.8)]">
                            {isForgot ? <Mail className="size-8" /> : <LockKeyhole className="size-8" />}
                        </div>
                        <h2 className="mt-7 text-2xl font-black tracking-tight">{title}</h2>
                        <p className="mt-3 text-sm font-semibold leading-6 text-slate-500">{subtitle}</p>
                    </div>

                    <form onSubmit={submit} className="mt-7 space-y-5">
                        <input type="hidden" name="panel" value={activePanel} />

                        {flash?.success ? (
                            <div className="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                <p>{flash.success}</p>
                            </div>
                        ) : null}

                        {isRegister ? (
                            <>
                                <Field label="Full name" id="name" error={form.errors.name}>
                                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="h-12 bg-slate-50 font-semibold" autoComplete="name" required />
                                </Field>
                                {isSeller ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Store name" id="store_name" error={form.errors.store_name}>
                                            <Input id="store_name" value={form.data.store_name} onChange={(e) => form.setData('store_name', e.target.value)} className="h-12 bg-slate-50 font-semibold" />
                                        </Field>
                                        <Field label="Legal name" id="legal_name" error={form.errors.legal_name}>
                                            <Input id="legal_name" value={form.data.legal_name} onChange={(e) => form.setData('legal_name', e.target.value)} className="h-12 bg-slate-50 font-semibold" />
                                        </Field>
                                    </div>
                                ) : null}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Country" id="country_code" error={form.errors.country_code}>
                                        <select id="country_code" value={form.data.country_code} onChange={(e) => form.setData('country_code', e.target.value)} className="h-12 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                                            <option value="BD">Bangladesh</option>
                                            <option value="US">United States</option>
                                            <option value="GB">United Kingdom</option>
                                            <option value="AE">United Arab Emirates</option>
                                        </select>
                                    </Field>
                                    <Field label="Currency" id="currency" error={form.errors.currency}>
                                        <select id="currency" value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} className="h-12 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                                            <option value="BDT">BDT</option>
                                            <option value="USD">USD</option>
                                            <option value="GBP">GBP</option>
                                            <option value="AED">AED</option>
                                        </select>
                                    </Field>
                                </div>
                            </>
                        ) : null}

                        <Field label="Email address" id="email" error={form.errors.email}>
                            <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="h-12 bg-slate-50 font-semibold" autoComplete="username" placeholder="alex@company.com" required />
                        </Field>

                        {isRegister ? (
                            <Field label="Phone" id="phone" error={form.errors.phone}>
                                <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="h-12 bg-slate-50 font-semibold" autoComplete="tel" placeholder="+880..." />
                            </Field>
                        ) : null}

                        {!isForgot ? (
                            <Field
                                label="Password"
                                id="password"
                                error={form.errors.password}
                                action={!isRegister ? <Link href="/forgot-password" className="text-xs font-black text-indigo-600 hover:text-indigo-800">Forgot?</Link> : null}
                            >
                                <PasswordInput id="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete={isRegister ? 'new-password' : 'current-password'} />
                            </Field>
                        ) : null}

                        {isRegister ? (
                            <Field label="Confirm password" id="password_confirmation" error={form.errors.password_confirmation}>
                                <PasswordInput id="password_confirmation" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} autoComplete="new-password" />
                            </Field>
                        ) : null}

                        <Button type="submit" className="h-12 w-full rounded-lg bg-indigo-600 text-base font-black shadow-[0_18px_38px_-24px_rgba(79,70,229,0.95)] hover:bg-indigo-700" disabled={form.processing}>
                            {form.processing ? 'Please wait...' : submitLabel}
                            <ArrowRight className="size-4" />
                        </Button>
                    </form>

                    <p className="mt-6 text-center text-sm font-semibold text-slate-500">
                        {isForgot ? 'Remembered your password?' : (isRegister ? 'Already have access?' : 'New to Sellova?')}{' '}
                        <Link href={`${isRegister || isForgot ? '/login' : '/register'}?panel=${activePanel}`} className="font-black text-indigo-600 hover:text-indigo-800">
                            {isRegister || isForgot ? 'Sign in' : 'Create account'}
                        </Link>
                    </p>
                </section>
            </div>
        </main>
    );
}

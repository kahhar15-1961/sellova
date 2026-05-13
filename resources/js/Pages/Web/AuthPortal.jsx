import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    BriefcaseBusiness,
    CheckCircle2,
    ExternalLink,
    Eye,
    EyeOff,
    Globe2,
    LockKeyhole,
    Mail,
    Phone,
    ShieldCheck,
    ShoppingBag,
    Store,
    UserRound,
    WalletCards,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

function Field({ label, id, error, children, action }) {
    return (
        <div>
            <div className="mb-2 flex items-center justify-between gap-3">
                <label htmlFor={id} className="text-[11px] font-black uppercase tracking-normal text-slate-500">{label}</label>
                {action}
            </div>
            {children}
            {error ? <p className="mt-2 text-xs font-semibold text-rose-600">{error}</p> : null}
        </div>
    );
}

function PasswordInput({ id, value, onChange, autoComplete, required = true, inputClassName = 'h-12 bg-slate-50 pr-12 font-semibold' }) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input
                id={id}
                type={visible ? 'text' : 'password'}
                value={value}
                onChange={onChange}
                className={inputClassName}
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

export default function AuthPortal({ mode = 'login', panel = 'buyer', upgrade = null }) {
    const isRegister = mode === 'register';
    const isForgot = mode === 'forgot';
    const { flash } = usePage().props;
    const upgradeUser = upgrade?.user || {};
    const isSellerUpgrade = Boolean(upgrade?.enabled) && isRegister && panel === 'seller';
    const form = useForm({
        panel,
        name: upgradeUser.name || '',
        email: upgradeUser.email || '',
        phone: upgradeUser.phone || '',
        password: '',
        password_confirmation: '',
        store_name: upgradeUser.store_name || '',
        legal_name: upgradeUser.legal_name || '',
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
    const title = isForgot ? 'Recover Access' : (isSellerUpgrade ? 'Become a Seller' : (isRegister ? 'Create Account' : 'Welcome Back'));
    const subtitle = isForgot
        ? 'Enter your account email and we will start a secure recovery request.'
        : (isSellerUpgrade
            ? 'Turn your buyer account into a combined buyer and seller workspace without creating a second login.'
            : (isRegister ? 'Create one secure account for marketplace buying and seller operations.' : 'Sign in to access your secure vault, escrows, and workspace.'));
    const submitLabel = isForgot
        ? 'Send Recovery Request'
        : (isSellerUpgrade ? 'Activate Seller Workspace' : (isRegister ? (isSeller ? 'Create Seller Workspace' : 'Create Buyer Account') : 'Sign In Securely'));
    const sectionClassName = isSellerUpgrade
        ? 'mx-auto w-full max-w-[560px] rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6'
        : 'mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_28px_80px_-52px_rgba(15,23,42,0.75)] sm:p-8';
    const fieldClassName = isSellerUpgrade ? 'h-10 bg-white font-semibold' : 'h-12 bg-slate-50 font-semibold';
    const selectClassName = isSellerUpgrade
        ? 'h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900/10'
        : 'h-12 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20';
    const submitClassName = isSellerUpgrade
        ? 'h-10 w-full rounded-lg bg-slate-950 text-sm font-bold shadow-none hover:bg-slate-800'
        : 'h-12 w-full rounded-lg bg-indigo-600 text-sm font-black shadow-[0_18px_38px_-24px_rgba(79,70,229,0.95)] hover:bg-indigo-700';

    const sellerHighlights = [
        [UserRound, 'Unified Login', 'Switch roles instantly'],
        [ShieldCheck, 'Live Escrow', 'Secure transactions'],
        [Globe2, 'Local Ready', 'Optimized for BDT'],
    ];
    const sellerSteps = [
        ['01', 'Confirm Profile', 'Verify your identity and provide core business details to establish trust.'],
        ['02', 'Activate Workspace', 'Generate your digital storefront and configure your dashboard settings.'],
        ['03', 'Launch Operations', 'List products, manage orders, and start receiving secure payouts.'],
    ];
    const buyerHighlights = [
        [ShieldCheck, 'Escrow protected', 'Funds stay protected until order milestones are completed.'],
        [WalletCards, 'Wallet ready', 'Local currency, payment methods, and order history in one secure workspace.'],
        [BadgeCheck, 'Trusted profiles', 'Buy from storefronts with verification, reviews, and marketplace signals.'],
    ];
    const buyerFlow = [
        ['01', 'Create secure identity'],
        ['02', 'Browse verified sellers'],
        ['03', 'Checkout with escrow'],
    ];
    const accessHighlights = [
        [ShieldCheck, 'Protected sessions', 'Role-aware access with secure workspace routing.'],
        [BriefcaseBusiness, 'Buyer and seller', 'Move between marketplace, orders, storefronts, and payouts.'],
        [BadgeCheck, 'Verified signals', 'Trust, notifications, reviews, and escrow state stay connected.'],
    ];

    if (isSellerUpgrade) {
        return (
            <main className="min-h-screen overflow-hidden bg-[#f6f8fb] text-slate-950">
                <Head title="Become a seller" />
                <div className="grid min-h-screen lg:grid-cols-[0.76fr_1fr]">
                    <section className="relative overflow-hidden bg-[#060b14] px-5 py-6 text-white sm:px-8 lg:px-10 xl:px-14">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_72%_12%,rgba(94,76,255,0.20),transparent_34%),radial-gradient(circle_at_4%_95%,rgba(11,148,156,0.16),transparent_30%),linear-gradient(90deg,rgba(8,13,23,0.95),rgba(17,20,38,0.96))]" />
                        <div className="relative z-10 flex min-h-full flex-col">
                            <Link href="/" className="inline-flex w-fit items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-[#5947f4] text-white shadow-[0_22px_42px_-22px_rgba(89,71,244,0.9)]">
                                    <ShoppingBag className="size-6" />
                                </span>
                                <span className="text-2xl font-black tracking-normal">Sellova</span>
                            </Link>

                            <div className="mt-10 inline-flex w-fit items-center gap-3 rounded-full border border-white/12 bg-white/10 px-4 py-1.5 text-xs font-black text-indigo-200 shadow-inner shadow-white/5">
                                <ExternalLink className="size-4" />
                                Professional Workspace Upgrade
                            </div>

                            <div className="mt-7 max-w-2xl">
                                <h1 className="text-4xl font-black leading-[1.04] tracking-normal sm:text-5xl xl:text-[3.25rem]">
                                    Empower your journey <span className="block text-[#7d8cff]">as a merchant.</span>
                                </h1>
                                <p className="mt-4 max-w-lg text-sm font-semibold leading-6 text-slate-400">
                                    Transform your buyer profile into a powerful commerce engine with storefront setup, integrated escrow, and professional payout tools.
                                </p>
                            </div>

                            <div className="mt-9 grid max-w-2xl gap-3 sm:grid-cols-3">
                                {sellerHighlights.map(([Icon, itemTitle, body]) => (
                                    <div key={itemTitle} className="rounded-xl border border-white/10 bg-white/10 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)] transition hover:-translate-y-1 hover:border-indigo-400/50 hover:bg-white/[0.13]">
                                        <Icon className="size-5 text-[#808aff]" />
                                        <p className="mt-5 text-sm font-black text-white">{itemTitle}</p>
                                        <p className="mt-2 text-[11px] font-black uppercase leading-5 tracking-[0.12em] text-slate-500">{body}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-10 max-w-2xl">
                                <p className="text-xs font-black uppercase tracking-[0.18em] text-slate-500">The process</p>
                                <div className="mt-6 space-y-6">
                                    {sellerSteps.map(([number, stepTitle, body], index) => (
                                        <div key={number} className="relative flex gap-4">
                                            {index < sellerSteps.length - 1 ? (
                                                <span className="absolute left-[22px] top-11 h-[calc(100%+1.5rem)] w-px bg-indigo-400/15" />
                                            ) : null}
                                            <span className="relative z-10 flex size-11 shrink-0 items-center justify-center rounded-full border border-indigo-400/30 bg-indigo-500/20 text-sm font-black text-indigo-300 shadow-[0_0_30px_-18px_rgba(125,140,255,1)]">
                                                {number}
                                            </span>
                                            <span className="pt-1">
                                                <span className="block text-sm font-black text-white">{stepTitle}</span>
                                                <span className="mt-1.5 block max-w-xl text-sm font-semibold leading-6 text-slate-500">{body}</span>
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="mt-auto pt-8">
                                <div className="flex items-center gap-4 rounded-xl border border-indigo-400/20 bg-indigo-500/10 px-4 py-3 text-indigo-200">
                                    <span className="size-3 rounded-full bg-indigo-400" />
                                    <p className="text-sm font-black">Your shopping history remains fully preserved.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="flex items-start justify-center px-4 py-5 sm:px-8 lg:px-10 lg:pt-8 xl:pt-10">
                        <div className="w-full max-w-[680px] overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_32px_80px_-64px_rgba(15,23,42,0.65)]">
                            <div className="px-5 py-5 sm:px-7 lg:px-8">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="flex size-10 items-center justify-center rounded-xl bg-slate-50 text-slate-600">
                                            <Building2 className="size-5" />
                                        </div>
                                        <h2 className="mt-4 text-xl font-black tracking-normal text-slate-950">Business Details</h2>
                                        <p className="mt-1 text-sm font-semibold leading-6 text-slate-500">Configure your professional identity</p>
                                    </div>
                                    <div className="hidden rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-2 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700 sm:block">
                                        Ready to upgrade
                                    </div>
                                </div>

                                <form onSubmit={submit} className="mt-5 space-y-3">
                                    <input type="hidden" name="panel" value={activePanel} />

                                    {flash?.success ? (
                                        <div className="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                                            <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                            <p>{flash.success}</p>
                                        </div>
                                    ) : null}

                                    <Field label="Account owner name" id="name" error={form.errors.name}>
                                        <div className="relative">
                                            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="h-10 rounded-xl border-slate-200 bg-white px-4 pr-11 text-sm font-black text-slate-700 shadow-none focus-visible:ring-[#5b4cf6]/20" autoComplete="name" required />
                                            <UserRound className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                        </div>
                                    </Field>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Store name" id="store_name" error={form.errors.store_name}>
                                            <div className="relative">
                                                <Input id="store_name" value={form.data.store_name} onChange={(e) => form.setData('store_name', e.target.value)} className="h-10 rounded-xl border-slate-200 bg-white px-4 pr-11 text-sm font-black text-slate-700 shadow-none focus-visible:ring-[#5b4cf6]/20" />
                                                <Store className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                            </div>
                                        </Field>
                                        <Field label="Legal name" id="legal_name" error={form.errors.legal_name}>
                                            <div className="relative">
                                                <Input id="legal_name" value={form.data.legal_name} onChange={(e) => form.setData('legal_name', e.target.value)} className="h-10 rounded-xl border-slate-200 bg-white px-4 pr-11 text-sm font-black text-slate-700 shadow-none focus-visible:ring-[#5b4cf6]/20" />
                                                <BadgeCheck className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                            </div>
                                        </Field>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Country" id="country_code" error={form.errors.country_code}>
                                            <div className="relative">
                                                <span className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 rounded-full bg-emerald-600 after:absolute after:left-1/2 after:top-1/2 after:size-2 after:-translate-x-1/2 after:-translate-y-1/2 after:rounded-full after:bg-rose-500" />
                                                <select id="country_code" value={form.data.country_code} onChange={(e) => form.setData('country_code', e.target.value)} className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-11 text-sm font-black text-slate-500 shadow-none focus:outline-none focus:ring-2 focus:ring-[#5b4cf6]/20">
                                                    <option value="BD">Bangladesh</option>
                                                    <option value="US">United States</option>
                                                    <option value="GB">United Kingdom</option>
                                                    <option value="AE">United Arab Emirates</option>
                                                </select>
                                            </div>
                                        </Field>
                                        <Field label="Currency" id="currency" error={form.errors.currency}>
                                            <div className="relative">
                                                <WalletCards className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                                <select id="currency" value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-11 text-sm font-black text-slate-500 shadow-none focus:outline-none focus:ring-2 focus:ring-[#5b4cf6]/20">
                                                    <option value="BDT">BDT</option>
                                                    <option value="USD">USD</option>
                                                    <option value="GBP">GBP</option>
                                                    <option value="AED">AED</option>
                                                </select>
                                            </div>
                                        </Field>
                                    </div>

                                    <Field label="Primary email address" id="email" error={form.errors.email}>
                                        <div className="relative">
                                            <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="h-10 rounded-xl border-slate-200 bg-white px-4 pr-11 text-sm font-black text-slate-700 shadow-none focus-visible:ring-[#5b4cf6]/20" autoComplete="username" required readOnly />
                                            <Mail className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                        </div>
                                    </Field>

                                    <Field label="Mobile number" id="phone" error={form.errors.phone}>
                                        <div className="relative">
                                            <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="h-10 rounded-xl border-slate-200 bg-white px-4 pr-11 text-sm font-black text-slate-400 shadow-none placeholder:text-slate-400 focus-visible:ring-[#5b4cf6]/20" autoComplete="tel" placeholder="+880 XXXXXXXXXX" />
                                            <Phone className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                        </div>
                                    </Field>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="flex h-10 items-center gap-3 rounded-xl bg-slate-50 px-4 text-xs font-black text-slate-600">
                                            <CheckCircle2 className="size-4 text-emerald-500" />
                                            Secure Settlement
                                        </div>
                                        <div className="flex h-10 items-center gap-3 rounded-xl bg-slate-50 px-4 text-xs font-black text-slate-600">
                                            <CheckCircle2 className="size-4 text-emerald-500" />
                                            Escrow Protected
                                        </div>
                                    </div>

                                    <Button type="submit" className="h-11 w-full rounded-xl bg-[#5748f1] text-sm font-black shadow-[0_18px_28px_-18px_rgba(87,72,241,0.9)] transition hover:-translate-y-0.5 hover:bg-[#4739db]" disabled={form.processing}>
                                        {form.processing ? 'Activating workspace...' : 'Activate Seller Workspace'}
                                        <ArrowRight className="size-4" />
                                    </Button>
                                </form>

                                <p className="mt-4 text-center text-xs font-bold text-slate-400">
                                    Need a standard workspace instead? <Link href="/dashboard" className="font-black text-[#5748f1] underline decoration-[#5748f1]/40 underline-offset-4 hover:text-[#4739db]">Return to dashboard</Link>
                                </p>
                            </div>
                            <div className="border-t border-slate-100 bg-[#f3f5ff] px-5 py-4 text-center text-xs font-semibold leading-6 text-[#675bff] sm:px-10">
                                By activating, you agree to our Merchant Terms of Service and Professional Conduct Guidelines.
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        );
    }

    if (isRegister && !isSeller) {
        return (
            <main className="min-h-screen overflow-hidden bg-[#f5f7fb] text-slate-950">
                <Head title="Create buyer account" />
                <div className="grid min-h-screen lg:grid-cols-[0.76fr_1fr]">
                    <section className="relative overflow-hidden bg-slate-950 px-5 py-6 text-white sm:px-8 lg:px-10 xl:px-14">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_76%_12%,rgba(87,72,241,0.24),transparent_34%),radial-gradient(circle_at_8%_90%,rgba(20,184,166,0.18),transparent_28%)]" />
                        <div className="relative z-10 flex min-h-full flex-col">
                            <Link href="/" className="inline-flex w-fit items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-[#5748f1] text-white shadow-[0_18px_36px_-22px_rgba(87,72,241,0.95)]">
                                    <ShoppingBag className="size-6" />
                                </span>
                                <span className="text-2xl font-black tracking-normal">Sellova</span>
                            </Link>

                            <div className="mt-10 inline-flex w-fit items-center gap-2 rounded-full border border-white/12 bg-white/10 px-4 py-1.5 text-xs font-black text-indigo-100">
                                <LockKeyhole className="size-4" />
                                Secure buyer workspace
                            </div>

                            <div className="mt-7 max-w-2xl">
                                <h1 className="text-4xl font-black leading-[1.04] tracking-normal sm:text-5xl xl:text-[3.3rem]">
                                    Buy with confidence in a protected marketplace.
                                </h1>
                                <p className="mt-4 max-w-xl text-sm font-semibold leading-6 text-slate-400">
                                    Create an enterprise-grade buyer account for escrow checkout, trusted seller discovery, order tracking, wallet activity, and secure communication.
                                </p>
                            </div>

                            <div className="mt-9 grid gap-3 md:grid-cols-3">
                                {buyerHighlights.map(([Icon, itemTitle, body]) => (
                                    <div key={itemTitle} className="rounded-xl border border-white/10 bg-white/10 p-4 transition hover:-translate-y-1 hover:border-indigo-300/50 hover:bg-white/[0.13]">
                                        <Icon className="size-5 text-[#8a94ff]" />
                                        <p className="mt-4 text-sm font-black text-white">{itemTitle}</p>
                                        <p className="mt-2 text-xs font-semibold leading-5 text-slate-500">{body}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-8 grid gap-4 xl:grid-cols-[1fr_0.9fr]">
                                <div className="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Checkout assurance</p>
                                        <span className="rounded-full bg-emerald-400/10 px-3 py-1 text-[11px] font-black text-emerald-300">Live</span>
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        {[
                                            ['Escrow hold', 'Funds protected before release'],
                                            ['Seller verified', 'Profile, reviews, and dispute signals'],
                                            ['Order timeline', 'Delivery and review checkpoints'],
                                        ].map(([label, body]) => (
                                            <div key={label} className="flex items-center gap-3 rounded-xl bg-slate-950/40 p-3">
                                                <CheckCircle2 className="size-4 text-emerald-300" />
                                                <span>
                                                    <span className="block text-sm font-black text-white">{label}</span>
                                                    <span className="block text-xs font-semibold text-slate-500">{body}</span>
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                    <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Account flow</p>
                                    <div className="mt-4 space-y-3">
                                        {buyerFlow.map(([number, label]) => (
                                            <div key={number} className="flex items-center gap-3">
                                                <span className="flex size-8 items-center justify-center rounded-lg bg-indigo-500/20 text-xs font-black text-indigo-200">{number}</span>
                                                <span className="text-sm font-black text-slate-200">{label}</span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-5 rounded-xl border border-indigo-300/20 bg-indigo-500/10 p-3 text-xs font-semibold leading-5 text-indigo-100">
                                        Your buyer account can be upgraded to seller later without creating a second login.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="flex items-start justify-center px-4 py-5 sm:px-8 lg:px-10 lg:pt-8 xl:pt-10">
                        <div className="w-full max-w-[680px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_30px_80px_-64px_rgba(15,23,42,0.75)]">
                            <div className="border-b border-slate-100 bg-white px-5 py-4 sm:px-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-[#5748f1]">
                                            <UserRound className="size-5" />
                                        </div>
                                        <h2 className="mt-4 text-xl font-black tracking-normal text-slate-950">Create buyer account</h2>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">Secure checkout, saved activity, and protected orders.</p>
                                    </div>
                                    <span className="hidden rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700 sm:inline-flex">
                                        Protected
                                    </span>
                                </div>
                            </div>

                            <form onSubmit={submit} className="space-y-3 px-5 py-4 sm:px-6">
                                <input type="hidden" name="panel" value={activePanel} />

                                {flash?.success ? (
                                    <div className="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                        <p>{flash.success}</p>
                                    </div>
                                ) : null}

                                <Field label="Full name" id="name" error={form.errors.name}>
                                    <div className="relative">
                                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="h-10 rounded-xl bg-white px-4 pr-11 text-sm font-bold shadow-none focus-visible:ring-[#5748f1]/20" autoComplete="name" required />
                                        <UserRound className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                    </div>
                                </Field>

                                <Field label="Email address" id="email" error={form.errors.email}>
                                    <div className="relative">
                                        <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="h-10 rounded-xl bg-white px-4 pr-11 text-sm font-bold shadow-none focus-visible:ring-[#5748f1]/20" autoComplete="username" placeholder="buyer@example.com" required />
                                        <Mail className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                    </div>
                                </Field>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Country" id="country_code" error={form.errors.country_code}>
                                        <select id="country_code" value={form.data.country_code} onChange={(e) => form.setData('country_code', e.target.value)} className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-600 focus:outline-none focus:ring-2 focus:ring-[#5748f1]/20">
                                            <option value="BD">Bangladesh</option>
                                            <option value="US">United States</option>
                                            <option value="GB">United Kingdom</option>
                                            <option value="AE">United Arab Emirates</option>
                                        </select>
                                    </Field>
                                    <Field label="Currency" id="currency" error={form.errors.currency}>
                                        <select id="currency" value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-600 focus:outline-none focus:ring-2 focus:ring-[#5748f1]/20">
                                            <option value="BDT">BDT</option>
                                            <option value="USD">USD</option>
                                            <option value="GBP">GBP</option>
                                            <option value="AED">AED</option>
                                        </select>
                                    </Field>
                                </div>

                                <Field label="Phone" id="phone" error={form.errors.phone}>
                                    <div className="relative">
                                        <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="h-10 rounded-xl bg-white px-4 pr-11 text-sm font-bold shadow-none placeholder:text-slate-400 focus-visible:ring-[#5748f1]/20" autoComplete="tel" placeholder="+880..." />
                                        <Phone className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                    </div>
                                </Field>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Password" id="password" error={form.errors.password}>
                                        <PasswordInput id="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password" inputClassName="h-10 rounded-xl bg-white pr-11 text-sm font-bold shadow-none focus-visible:ring-[#5748f1]/20" />
                                    </Field>

                                    <Field label="Confirm password" id="password_confirmation" error={form.errors.password_confirmation}>
                                        <PasswordInput id="password_confirmation" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} autoComplete="new-password" inputClassName="h-10 rounded-xl bg-white pr-11 text-sm font-bold shadow-none focus-visible:ring-[#5748f1]/20" />
                                    </Field>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="flex h-10 items-center gap-3 rounded-xl bg-slate-50 px-4 text-xs font-black text-slate-600">
                                        <ShieldCheck className="size-4 text-emerald-500" />
                                        Escrow checkout
                                    </div>
                                    <div className="flex h-10 items-center gap-3 rounded-xl bg-slate-50 px-4 text-xs font-black text-slate-600">
                                        <BadgeCheck className="size-4 text-emerald-500" />
                                        Verified signals
                                    </div>
                                </div>

                                <Button type="submit" className="h-10 w-full rounded-xl bg-[#5748f1] text-sm font-black shadow-[0_18px_28px_-18px_rgba(87,72,241,0.9)] hover:bg-[#4739db]" disabled={form.processing}>
                                    {form.processing ? 'Creating account...' : 'Create Buyer Account'}
                                    <ArrowRight className="size-4" />
                                </Button>
                            </form>

                            <div className="border-t border-slate-100 bg-slate-50 px-5 py-3 text-center text-xs font-semibold text-slate-500 sm:px-6">
                                Already have access? <Link href="/login" className="font-black text-[#5748f1] hover:text-[#4739db]">Sign in securely</Link>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        );
    }

    if (!isRegister) {
        const isLogin = !isForgot;
        const accessTitle = isForgot ? 'Recover secure access.' : 'Welcome back to your operating workspace.';
        const accessCopy = isForgot
            ? 'Start a recovery request for your Sellova account. We will guide you back into your protected buyer and seller workflows.'
            : 'Sign in once to continue protected buying, seller operations, escrow tracking, notifications, and wallet activity.';
        const panelStatus = isForgot ? 'Recovery' : (activePanel === 'seller' ? 'Seller access' : 'Buyer access');

        return (
            <main className="min-h-screen overflow-hidden bg-[#f5f7fb] text-slate-950">
                <Head title={isForgot ? 'Recover access' : 'Sign in'} />
                <div className="grid min-h-screen lg:grid-cols-[0.76fr_1fr]">
                    <section className="relative overflow-hidden bg-[#060b14] px-5 py-6 text-white sm:px-8 lg:px-10 xl:px-14">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_70%_10%,rgba(87,72,241,0.22),transparent_34%),radial-gradient(circle_at_8%_92%,rgba(20,184,166,0.15),transparent_28%),linear-gradient(90deg,rgba(8,13,23,0.97),rgba(16,19,37,0.96))]" />
                        <div className="relative z-10 flex min-h-full flex-col">
                            <Link href="/" className="inline-flex w-fit items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-[#5748f1] text-white shadow-[0_18px_36px_-22px_rgba(87,72,241,0.95)]">
                                    <ShoppingBag className="size-6" />
                                </span>
                                <span className="text-2xl font-black tracking-normal">Sellova</span>
                            </Link>

                            <div className="mt-10 inline-flex w-fit items-center gap-2 rounded-full border border-white/12 bg-white/10 px-4 py-1.5 text-xs font-black text-indigo-100">
                                <LockKeyhole className="size-4" />
                                {isForgot ? 'Account recovery' : 'Secure workspace access'}
                            </div>

                            <div className="mt-7 max-w-2xl">
                                <h1 className="text-4xl font-black leading-[1.04] tracking-normal sm:text-5xl xl:text-[3.25rem]">
                                    {accessTitle}
                                </h1>
                                <p className="mt-4 max-w-xl text-sm font-semibold leading-6 text-slate-400">
                                    {accessCopy}
                                </p>
                            </div>

                            <div className="mt-9 grid gap-3 md:grid-cols-3">
                                {accessHighlights.map(([Icon, itemTitle, body]) => (
                                    <div key={itemTitle} className="rounded-xl border border-white/10 bg-white/10 p-4 transition hover:-translate-y-1 hover:border-indigo-300/50 hover:bg-white/[0.13]">
                                        <Icon className="size-5 text-[#8a94ff]" />
                                        <p className="mt-4 text-sm font-black text-white">{itemTitle}</p>
                                        <p className="mt-2 text-xs font-semibold leading-5 text-slate-500">{body}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-8 grid gap-4 xl:grid-cols-[1fr_0.9fr]">
                                <div className="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Workspace status</p>
                                        <span className="rounded-full bg-emerald-400/10 px-3 py-1 text-[11px] font-black text-emerald-300">Online</span>
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        {[
                                            ['Escrow timeline', 'Protected orders and release checkpoints'],
                                            ['Notification center', 'Unread actions and account updates'],
                                            ['Role routing', 'Buyer dashboard or seller workspace'],
                                        ].map(([label, body]) => (
                                            <div key={label} className="flex items-center gap-3 rounded-xl bg-slate-950/40 p-3">
                                                <CheckCircle2 className="size-4 text-emerald-300" />
                                                <span>
                                                    <span className="block text-sm font-black text-white">{label}</span>
                                                    <span className="block text-xs font-semibold text-slate-500">{body}</span>
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                    <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Access modes</p>
                                    <div className="mt-4 grid gap-3">
                                        {[
                                            ['Buyer', 'Orders, cart, wallet, reviews'],
                                            ['Seller', 'Products, fulfillment, payouts'],
                                            ['Support', 'Messages, disputes, returns'],
                                        ].map(([label, body]) => (
                                            <div key={label} className="rounded-xl bg-slate-950/40 p-3">
                                                <p className="text-sm font-black text-slate-100">{label}</p>
                                                <p className="mt-1 text-xs font-semibold text-slate-500">{body}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-auto pt-8">
                                <div className="flex items-center gap-4 rounded-xl border border-indigo-400/20 bg-indigo-500/10 px-4 py-3 text-indigo-200">
                                    <span className="size-3 rounded-full bg-indigo-400" />
                                    <p className="text-sm font-black">Your marketplace activity stays private, connected, and recoverable.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="flex min-h-screen items-center justify-center px-4 py-8 sm:px-8 lg:px-10">
                        <div className="w-full max-w-[540px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_30px_80px_-64px_rgba(15,23,42,0.75)]">
                            <div className="border-b border-slate-100 bg-white px-5 py-5 sm:px-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-[#5748f1]">
                                            {isForgot ? <Mail className="size-5" /> : <LockKeyhole className="size-5" />}
                                        </div>
                                        <h2 className="mt-4 text-xl font-black tracking-normal text-slate-950">
                                            {isForgot ? 'Recover access' : 'Sign in securely'}
                                        </h2>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">
                                            {isForgot ? 'Enter your account email to begin recovery.' : 'Continue into your protected Sellova workspace.'}
                                        </p>
                                    </div>
                                    <span className="hidden rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700 sm:inline-flex">
                                        {panelStatus}
                                    </span>
                                </div>
                            </div>

                            <form onSubmit={submit} className="space-y-4 px-5 py-5 sm:px-6">
                                <input type="hidden" name="panel" value={activePanel} />

                                {flash?.success ? (
                                    <div className="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                        <p>{flash.success}</p>
                                    </div>
                                ) : null}

                                <Field label="Email address" id="email" error={form.errors.email}>
                                    <div className="relative">
                                        <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="h-11 rounded-xl bg-white px-4 pr-11 text-sm font-bold shadow-none focus-visible:ring-[#5748f1]/20" autoComplete="username" placeholder="you@example.com" required />
                                        <Mail className="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-300" />
                                    </div>
                                </Field>

                                {isLogin ? (
                                    <Field
                                        label="Password"
                                        id="password"
                                        error={form.errors.password}
                                        action={<Link href="/forgot-password" className="text-xs font-black text-[#5748f1] hover:text-[#4739db]">Forgot?</Link>}
                                    >
                                        <PasswordInput id="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="current-password" inputClassName="h-11 rounded-xl bg-white pr-11 text-sm font-bold shadow-none focus-visible:ring-[#5748f1]/20" />
                                    </Field>
                                ) : null}

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="flex h-10 items-center gap-3 rounded-xl bg-slate-50 px-4 text-xs font-black text-slate-600">
                                        <ShieldCheck className="size-4 text-emerald-500" />
                                        Session protected
                                    </div>
                                    <div className="flex h-10 items-center gap-3 rounded-xl bg-slate-50 px-4 text-xs font-black text-slate-600">
                                        <BadgeCheck className="size-4 text-emerald-500" />
                                        Role aware
                                    </div>
                                </div>

                                <Button type="submit" className="h-11 w-full rounded-xl bg-[#5748f1] text-sm font-black shadow-[0_18px_28px_-18px_rgba(87,72,241,0.9)] hover:bg-[#4739db]" disabled={form.processing}>
                                    {form.processing ? 'Please wait...' : (isForgot ? 'Send Recovery Request' : 'Sign In Securely')}
                                    <ArrowRight className="size-4" />
                                </Button>
                            </form>

                            <div className="border-t border-slate-100 bg-slate-50 px-5 py-4 text-center text-xs font-semibold text-slate-500 sm:px-6">
                                {isForgot ? 'Remembered your password?' : 'New to Sellova?'}{' '}
                                <Link href={isForgot ? '/login' : '/register'} className="font-black text-[#5748f1] hover:text-[#4739db]">
                                    {isForgot ? 'Sign in securely' : 'Create buyer account'}
                                </Link>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        );
    }

    return (
        <main className={isSellerUpgrade ? 'min-h-screen bg-slate-50 px-4 py-8 text-slate-950' : 'min-h-screen bg-[radial-gradient(circle_at_top_right,#e8edff,transparent_34%),linear-gradient(135deg,#f8fafc,#ffffff_45%,#f4f7fb)] px-4 py-8 text-slate-950'}>
            <Head title={isForgot ? 'Recover access' : (isRegister ? 'Create account' : 'Sign in')} />
            <div className={isSellerUpgrade ? 'mx-auto flex min-h-[calc(100vh-4rem)] max-w-3xl items-center justify-center' : 'mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl items-center gap-8 lg:grid-cols-[1fr_440px]'}>
                {!isSellerUpgrade ? (
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
                ) : null}

                <section className={sectionClassName}>
                    <div className={isSellerUpgrade ? '' : 'text-center'}>
                        <div className={isSellerUpgrade ? 'flex size-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-700' : 'mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-[0_18px_38px_-24px_rgba(15,23,42,0.8)]'}>
                            {isForgot ? <Mail className="size-8" /> : <LockKeyhole className="size-8" />}
                        </div>
                        <h2 className={isSellerUpgrade ? 'mt-4 text-xl font-black tracking-tight text-slate-950' : 'mt-7 text-2xl font-black tracking-tight'}>{title}</h2>
                        <p className={isSellerUpgrade ? 'mt-2 max-w-xl text-sm font-medium leading-6 text-slate-500' : 'mt-3 text-sm font-semibold leading-6 text-slate-500'}>{subtitle}</p>
                    </div>

                    <form onSubmit={submit} className={isSellerUpgrade ? 'mt-6 space-y-4' : 'mt-7 space-y-5'}>
                        <input type="hidden" name="panel" value={activePanel} />

                        {flash?.success ? (
                            <div className="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                <p>{flash.success}</p>
                            </div>
                        ) : null}

                        {isSellerUpgrade ? (
                            <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm">
                                <p className="font-bold text-slate-950">Same account seller onboarding</p>
                                <p className="mt-1 font-medium leading-6 text-slate-600">Your current buyer login will stay the same. We’ll add seller access and create your storefront.</p>
                            </div>
                        ) : null}

                        {isRegister ? (
                            <>
                                <Field label={isSellerUpgrade ? 'Account owner name' : 'Full name'} id="name" error={form.errors.name}>
                                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={fieldClassName} autoComplete="name" required />
                                </Field>
                                {isSeller ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Store name" id="store_name" error={form.errors.store_name}>
                                            <Input id="store_name" value={form.data.store_name} onChange={(e) => form.setData('store_name', e.target.value)} className={fieldClassName} />
                                        </Field>
                                        <Field label="Legal name" id="legal_name" error={form.errors.legal_name}>
                                            <Input id="legal_name" value={form.data.legal_name} onChange={(e) => form.setData('legal_name', e.target.value)} className={fieldClassName} />
                                        </Field>
                                    </div>
                                ) : null}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Country" id="country_code" error={form.errors.country_code}>
                                        <select id="country_code" value={form.data.country_code} onChange={(e) => form.setData('country_code', e.target.value)} className={selectClassName}>
                                            <option value="BD">Bangladesh</option>
                                            <option value="US">United States</option>
                                            <option value="GB">United Kingdom</option>
                                            <option value="AE">United Arab Emirates</option>
                                        </select>
                                    </Field>
                                    <Field label="Currency" id="currency" error={form.errors.currency}>
                                        <select id="currency" value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} className={selectClassName}>
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
                            <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className={fieldClassName} autoComplete="username" placeholder="alex@company.com" required readOnly={isSellerUpgrade} />
                        </Field>

                        {isRegister ? (
                            <Field label={isSellerUpgrade ? 'Mobile number' : 'Phone'} id="phone" error={form.errors.phone}>
                                <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className={fieldClassName} autoComplete="tel" placeholder="+880..." />
                            </Field>
                        ) : null}

                        {!isForgot && !isSellerUpgrade ? (
                            <Field
                                label="Password"
                                id="password"
                                error={form.errors.password}
                                action={!isRegister ? <Link href="/forgot-password" className="text-xs font-black text-indigo-600 hover:text-indigo-800">Forgot?</Link> : null}
                            >
                                <PasswordInput id="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete={isRegister ? 'new-password' : 'current-password'} />
                            </Field>
                        ) : null}

                        {isRegister && !isSellerUpgrade ? (
                            <Field label="Confirm password" id="password_confirmation" error={form.errors.password_confirmation}>
                                <PasswordInput id="password_confirmation" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} autoComplete="new-password" />
                            </Field>
                        ) : null}

                        <Button type="submit" className={submitClassName} disabled={form.processing}>
                            {form.processing ? 'Please wait...' : submitLabel}
                            <ArrowRight className="size-4" />
                        </Button>
                    </form>

                    {isSellerUpgrade ? (
                        <p className="mt-5 text-center text-sm font-medium text-slate-500">
                            Need your buyer workspace instead? <Link href="/dashboard" className="font-bold text-slate-700 hover:text-slate-950">Return to dashboard</Link>
                        </p>
                    ) : (
                        <p className="mt-6 text-center text-sm font-semibold text-slate-500">
                            {isForgot ? 'Remembered your password?' : (isRegister ? 'Already have access?' : 'New to Sellova?')}{' '}
                            <Link href={isRegister || isForgot ? '/login' : '/register'} className="font-black text-indigo-600 hover:text-indigo-800">
                                {isRegister || isForgot ? 'Sign in' : 'Create account'}
                            </Link>
                        </p>
                    )}
                </section>
            </div>
        </main>
    );
}

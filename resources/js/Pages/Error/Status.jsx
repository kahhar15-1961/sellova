import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileQuestion, Headset, Home, RefreshCcw, Search, ShieldAlert, Workflow } from 'lucide-react';

function contentFor(status) {
    const code = Number(status || 404);

    if (code === 403) {
        return {
            code: '403',
            title: 'Access Restricted',
            body: 'You are not authorized to access this area. If you believe this is a mistake, please verify your account permissions.',
            footer: 'Trustcrow Security',
            dotClass: 'bg-rose-500',
            iconClass: 'text-rose-300',
            lineClass: 'from-rose-500/90 via-rose-400/50 to-transparent',
            glowClass: 'from-rose-500/14 via-fuchsia-500/8 to-transparent',
            primaryLabel: 'Return to Dashboard',
            primaryKind: 'link',
            secondaryLeftLabel: 'Go Back',
            secondaryLeftKind: 'back',
            secondaryRightLabel: 'Support',
            secondaryRightKind: 'support',
            Icon: ShieldAlert,
            SecondaryRightIcon: Headset,
        };
    }

    if (code === 419) {
        return {
            code: '419',
            title: 'Page Expired',
            body: 'Your secure session expired before the request could be completed. Refresh the page and sign in again if needed.',
            footer: 'Trustcrow Session Guard',
            dotClass: 'bg-sky-500',
            iconClass: 'text-sky-300',
            lineClass: 'from-sky-500/90 via-cyan-400/50 to-transparent',
            glowClass: 'from-sky-500/14 via-cyan-500/8 to-transparent',
            primaryLabel: 'Refresh Session',
            primaryKind: 'refresh',
            secondaryLeftLabel: 'Dashboard',
            secondaryLeftKind: 'home',
            secondaryRightLabel: 'Support',
            secondaryRightKind: 'support',
            Icon: ShieldAlert,
            SecondaryRightIcon: Headset,
        };
    }

    if (code === 429) {
        return {
            code: '429',
            title: 'Too Many Requests',
            body: 'Traffic protection paused this request because too many actions were sent too quickly. Please wait a moment, then try again.',
            footer: 'Trustcrow Rate Guard',
            dotClass: 'bg-violet-500',
            iconClass: 'text-violet-300',
            lineClass: 'from-violet-500/90 via-indigo-400/50 to-transparent',
            glowClass: 'from-violet-500/14 via-indigo-500/8 to-transparent',
            primaryLabel: 'Try Again',
            primaryKind: 'refresh',
            secondaryLeftLabel: 'Dashboard',
            secondaryLeftKind: 'home',
            secondaryRightLabel: 'Support',
            secondaryRightKind: 'support',
            Icon: RefreshCcw,
            SecondaryRightIcon: Headset,
        };
    }

    if (code === 500) {
        return {
            code: '500',
            title: 'Internal Server Error',
            body: "We're experiencing an unexpected system fault. Our engineering team has been notified and is working to restore service.",
            footer: 'Trustcrow Engineering',
            dotClass: 'bg-amber-500',
            iconClass: 'text-amber-300',
            lineClass: 'from-amber-500/90 via-amber-400/50 to-transparent',
            glowClass: 'from-amber-500/16 via-orange-500/8 to-transparent',
            primaryLabel: 'Try Refreshing',
            primaryKind: 'refresh',
            secondaryLeftLabel: 'Dashboard',
            secondaryLeftKind: 'home',
            secondaryRightLabel: 'System Status',
            secondaryRightKind: 'support',
            Icon: Workflow,
            SecondaryRightIcon: Headset,
        };
    }

    return {
        code: '404',
        title: 'Page Not Found',
        body: "The page you are looking for doesn't exist or has been moved. Let's get you back on track.",
        footer: 'Trustcrow Navigation',
        dotClass: 'bg-indigo-500',
        iconClass: 'text-indigo-300',
        lineClass: 'from-indigo-500/90 via-violet-400/50 to-transparent',
        glowClass: 'from-indigo-500/14 via-violet-500/8 to-transparent',
        primaryLabel: 'Return to Dashboard',
        primaryKind: 'link',
        secondaryLeftLabel: 'Go Back',
        secondaryLeftKind: 'back',
        secondaryRightLabel: 'Search',
        secondaryRightKind: 'search',
        Icon: FileQuestion,
        SecondaryRightIcon: Search,
    };
}

function ActionButton({ kind = 'link', href = '/', label, children, primary = false }) {
    const baseClass = primary
        ? 'inline-flex h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 text-base font-black text-white transition hover:brightness-110'
        : 'inline-flex h-12 items-center justify-center gap-3 rounded-2xl border border-white/12 bg-white/5 px-4 text-sm font-black text-slate-100 transition hover:bg-white/10';

    if (kind === 'refresh') {
        return (
            <button
                type="button"
                onClick={() => window.location.reload()}
                className={`${baseClass} bg-[linear-gradient(135deg,#f59e0b_0%,#ea580c_100%)] shadow-[0_24px_60px_-30px_rgba(245,158,11,0.82)]`}
            >
                {children}
                {label}
            </button>
        );
    }

    if (kind === 'back') {
        return (
            <button
                type="button"
                onClick={() => window.history.back()}
                className={baseClass}
            >
                {children}
                {label}
            </button>
        );
    }

    return (
        <Link
            href={href}
            className={primary
                ? `${baseClass} bg-[linear-gradient(135deg,#4f46e5_0%,#5b4ff7_100%)] shadow-[0_24px_60px_-30px_rgba(99,102,241,0.9)]`
                : baseClass}
        >
            {children}
            {label}
        </Link>
    );
}

export default function Status({ status = 404, home_href = '/', support_href = '/support', search_href = '/marketplace' }) {
    const content = contentFor(status);
    const Icon = content.Icon;
    const SecondaryRightIcon = content.SecondaryRightIcon;
    const primaryHref = content.primaryKind === 'link' ? home_href : '#';
    const secondaryRightHref = content.secondaryRightKind === 'search' ? search_href : support_href;

    return (
        <>
            <Head title={`${content.code} ${content.title}`} />
            <div className="min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top,#eef2ff_0%,#ffffff_38%,#f8fafc_100%)] px-4 py-12 text-slate-950 sm:px-6 lg:px-8">
                <div className="mx-auto flex min-h-[calc(100vh-6rem)] max-w-5xl flex-col items-center justify-center">
                    <div className={`relative w-full max-w-[460px] overflow-hidden rounded-[36px] border border-slate-800/70 bg-[radial-gradient(circle_at_top,#171a2b_0%,#111827_38%,#0b1220_100%)] px-8 py-10 text-center text-white shadow-[0_45px_120px_-52px_rgba(15,23,42,0.85)]`}>
                        <div className={`pointer-events-none absolute inset-x-12 top-0 h-40 bg-gradient-to-b ${content.glowClass} blur-3xl`} />

                        <div className="relative mx-auto flex h-24 w-24 items-center justify-center rounded-[28px] border border-white/10 bg-white/5 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]">
                            <div className="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-white/5">
                                <Icon className={`h-8 w-8 ${content.iconClass}`} strokeWidth={2.25} />
                                <span className={`absolute -right-1 -bottom-1 h-3.5 w-3.5 rounded-full ${content.dotClass} shadow-[0_0_0_4px_rgba(15,23,42,0.92)]`} />
                            </div>
                        </div>

                        <h1 className="relative mt-8 text-7xl font-black tracking-tight">{content.code}</h1>
                        <p className="relative mt-3 text-[2rem] font-black tracking-tight text-white/95">{content.title}</p>
                        <div className={`relative mx-auto mt-5 h-1 w-14 rounded-full bg-gradient-to-r ${content.lineClass}`} />
                        <p className="relative mx-auto mt-7 max-w-sm text-base font-semibold leading-8 text-slate-300">
                            {content.body}
                        </p>

                        <div className="relative mt-9 space-y-3">
                            <ActionButton kind={content.primaryKind} href={primaryHref} label={content.primaryLabel} primary>
                                {content.primaryKind === 'refresh' ? <RefreshCcw className="h-4.5 w-4.5" /> : <Home className="h-4.5 w-4.5" />}
                            </ActionButton>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <ActionButton kind={content.secondaryLeftKind} href={home_href} label={content.secondaryLeftLabel}>
                                    {content.secondaryLeftKind === 'home' ? <Home className="h-4.5 w-4.5" /> : <ArrowLeft className="h-4.5 w-4.5" />}
                                </ActionButton>
                                <ActionButton kind={content.secondaryRightKind === 'support' ? 'link' : 'link'} href={secondaryRightHref} label={content.secondaryRightLabel}>
                                    <SecondaryRightIcon className="h-4.5 w-4.5" />
                                </ActionButton>
                            </div>
                        </div>
                    </div>

                    <div className="mt-10 inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.24em] text-slate-400">
                        <ShieldAlert className="h-4 w-4" />
                        {content.footer}
                    </div>
                </div>
            </div>
        </>
    );
}

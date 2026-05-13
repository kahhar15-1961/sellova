import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Eye, EyeOff, KeyRound, LockKeyhole, Mail, Shield, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const SUPPORT_EMAIL = 'admin@trustcrow.com';

function AuthLogo({ brand }) {
    const brandName = brand?.name || 'Sellova';
    const subtitle = brand?.subtitle || 'Backend System';

    return (
        <div className="flex items-center gap-3.5">
            <div className="flex h-11 w-11 items-center justify-center rounded-[14px] bg-[#081120] text-white shadow-[0_16px_34px_rgba(8,17,32,0.20)]">
                <ShieldCheck className="h-5 w-5" />
            </div>
            <div>
                <p className="text-[21px] font-extrabold leading-none tracking-[-0.035em] text-[#0f172a]">{brandName}</p>
                <p className="mt-1.5 text-[10px] font-extrabold uppercase tracking-[0.24em] text-[#584bff]">{subtitle}</p>
            </div>
        </div>
    );
}

function SecurityFooter({ brand }) {
    const footerName = brand?.footer || `${brand?.name || 'Sellova'} Escrow`;

    return (
        <div className="mt-10 border-t border-[#e6edf6] pt-7">
            <div className="flex items-center justify-between gap-4 text-[11px] font-bold text-[#91a1bb]">
                <span>© 2026 {footerName}</span>
                <span className="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-[11px] font-extrabold text-emerald-700">
                    <Shield className="h-3 w-3" />
                    Secure Connection
                </span>
            </div>
        </div>
    );
}

function FieldError({ message }) {
    if (!message) return null;

    return <p className="mt-1.5 text-xs font-semibold text-red-600">{message}</p>;
}

function AuthField({ id, label, icon: Icon, error, right, suffix, className, children: _children, ...props }) {
    return (
        <div className={className}>
            <div className="mb-2 flex items-center justify-between gap-3">
                <label htmlFor={id} className="text-[11px] font-extrabold uppercase tracking-[0.22em] text-[#667893]">
                    {label}
                </label>
                {right}
            </div>
            <div
                className={cn(
                    'flex h-11 w-full items-center gap-3 rounded-[11px] border border-[#d8e2ef] bg-[#f8fbff] px-3.5 text-[#17243a] shadow-none transition focus-within:border-[#9eb0ca] focus-within:bg-white',
                    error && 'border-red-300 bg-red-50/40',
                )}
            >
                <Icon className="h-4 w-4 shrink-0 text-[#8fa0ba]" />
                <input
                    id={id}
                    className="min-w-0 flex-1 border-0 bg-transparent p-0 text-[14px] font-bold leading-none text-[#17243a] outline-none placeholder:text-[#8fa0ba] focus:ring-0"
                    {...props}
                />
                {suffix}
            </div>
            <FieldError message={error} />
        </div>
    );
}

function LoginView({ brand }) {
    const [showPassword, setShowPassword] = useState(false);
    const { props } = usePage();
    const supportEmail = brand?.supportEmail || SUPPORT_EMAIL;
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    return (
        <>
            <AuthLogo brand={brand} />

            <div className="mt-10">
                <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.045em] text-[#111827]">Welcome back</h1>
                <p className="mt-2 text-[13px] font-semibold leading-5 text-[#657792]">Enter your credentials to access the escrow engine.</p>
                {props.status ? <p className="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">{props.status}</p> : null}
            </div>

            <form
                className="mt-9 space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/admin/login');
                }}
            >
                <AuthField
                    id="email"
                    label="Admin Email"
                    icon={Mail}
                    type="email"
                    autoComplete="username"
                    placeholder={supportEmail}
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                    required
                />

                <AuthField
                    id="password"
                    label="Password"
                    icon={LockKeyhole}
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    placeholder="••••••••••••"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                    required
                    right={
                        <Link href="/admin/forgot-password" className="text-[10px] font-extrabold text-[#584bff] hover:text-[#3f33d7]">
                            Forgot?
                        </Link>
                    }
                    suffix={
                        <button type="button" className="text-[#8fa0ba]" onClick={() => setShowPassword((value) => !value)} aria-label="Toggle password visibility">
                            {showPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                        </button>
                    }
                >
                    Password
                </AuthField>

                <button type="button" className="flex items-center gap-2 text-[12px] font-bold text-[#27364f]" onClick={() => form.setData('remember', !form.data.remember)}>
                    <span className={cn('flex h-3.5 w-3.5 items-center justify-center rounded-[3px] border border-[#9aabc3] bg-white', form.data.remember && 'border-[#584bff] bg-[#584bff]')}>
                        {form.data.remember ? <span className="h-1.5 w-1.5 rounded-sm bg-white" /> : null}
                    </span>
                    Remember my device for 30 days
                </button>

                <Button type="submit" className="h-12 w-full rounded-[12px] bg-[#081120] text-[13px] font-extrabold text-white shadow-[0_16px_30px_rgba(8,17,32,0.20)] hover:bg-[#111827]" disabled={form.processing}>
                    {form.processing ? 'Checking access...' : 'Access Backend'}
                    <ArrowRight className="h-3.5 w-3.5" />
                </Button>
            </form>

        </>
    );
}

function ForgotView({ brand }) {
    const supportEmail = brand?.supportEmail || SUPPORT_EMAIL;
    const form = useForm({ email: '' });

    return (
        <>
            <Link href="/admin/login" className="inline-flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8fa0ba] hover:text-[#17243a]">
                <ArrowLeft className="h-3.5 w-3.5" />
                Back to login
            </Link>

            <div className="mt-8 flex h-[52px] w-[52px] items-center justify-center rounded-xl border border-[#dce5ff] bg-[#f0f4ff] text-[#584bff] shadow-[0_12px_22px_rgba(88,75,255,0.10)]">
                <KeyRound className="h-6 w-6" />
            </div>

            <div className="mt-7">
                <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.045em] text-[#111827]">Recover Access</h1>
                <p className="mt-2 max-w-[360px] text-[13px] font-semibold leading-5 text-[#657792]">Enter your administrative email address to receive a secure recovery code.</p>
            </div>

            <form
                className="mt-9 space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/admin/forgot-password');
                }}
            >
                <AuthField
                    id="recovery-email"
                    label="Admin Email"
                    icon={Mail}
                    type="email"
                    autoComplete="username"
                    placeholder={supportEmail}
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                    required
                />

                <Button type="submit" className="h-12 w-full rounded-[12px] bg-[#081120] text-[13px] font-extrabold text-white shadow-[0_16px_30px_rgba(8,17,32,0.20)] hover:bg-[#111827]" disabled={form.processing}>
                    {form.processing ? 'Sending code...' : 'Send Verification Code'}
                </Button>
            </form>
        </>
    );
}

function OtpView({ recoveryEmail, brand }) {
    const supportEmail = brand?.supportEmail || SUPPORT_EMAIL;
    const refs = useRef([]);
    const [digits, setDigits] = useState(['', '', '', '', '', '']);
    const form = useForm({ code: '' });

    useEffect(() => {
        form.setData('code', digits.join(''));
    }, [digits]);

    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/password/otp');
    };

    return (
        <>
            <Link href="/admin/forgot-password" className="inline-flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8fa0ba] hover:text-[#17243a]">
                <ArrowLeft className="h-3.5 w-3.5" />
                Back
            </Link>

            <div className="mt-8 flex h-[52px] w-[52px] items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-600">
                <Shield className="h-6 w-6" />
            </div>

            <div className="mt-7">
                <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.045em] text-[#111827]">Security Check</h1>
                <p className="mt-2 max-w-[340px] text-[13px] font-semibold leading-5 text-[#657792]">
                    We've sent a 6-digit verification code to <span className="font-extrabold text-[#17243a]">{recoveryEmail || supportEmail}</span>.
                </p>
            </div>

            <form className="mt-9" onSubmit={submit}>
                <div className="flex justify-between gap-3">
                    {digits.map((digit, index) => (
                        <input
                            key={index}
                            ref={(element) => {
                                refs.current[index] = element;
                            }}
                            value={digit}
                            inputMode="numeric"
                            maxLength={1}
                            className="h-12 w-11 rounded-[11px] border border-[#d7e1ee] bg-[#f8fbff] text-center text-lg font-extrabold text-[#111827] outline-none transition focus:border-[#584bff] focus:bg-white"
                            onChange={(event) => {
                                const nextDigit = event.target.value.replace(/\D/g, '').slice(-1);
                                setDigits((current) => current.map((item, i) => (i === index ? nextDigit : item)));
                                if (nextDigit && index < 5) refs.current[index + 1]?.focus();
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Backspace' && !digits[index] && index > 0) refs.current[index - 1]?.focus();
                            }}
                        />
                    ))}
                </div>
                <FieldError message={form.errors.code} />

                <div className="mt-5 text-center text-[12px] font-bold text-[#657792]">
                    Didn't receive the code?{' '}
                    <button type="button" className="font-extrabold text-[#584bff]" onClick={() => router.post('/admin/password/otp/resend')} disabled={form.processing}>
                        Click to resend
                    </button>
                </div>

                <Button type="submit" className="mt-6 h-12 w-full rounded-[12px] bg-[#081120] text-[13px] font-extrabold text-white shadow-[0_16px_30px_rgba(8,17,32,0.20)] hover:bg-[#111827]" disabled={form.processing || digits.join('').length < 6}>
                    {form.processing ? 'Verifying...' : 'Verify Identity'}
                </Button>
            </form>
        </>
    );
}

function ResetView({ recoveryEmail, brand }) {
    const [showPassword, setShowPassword] = useState(false);
    const supportEmail = brand?.supportEmail || SUPPORT_EMAIL;
    const form = useForm({
        password: '',
        password_confirmation: '',
    });

    return (
        <>
            <Link href="/admin/password/otp" className="inline-flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8fa0ba] hover:text-[#17243a]">
                <ArrowLeft className="h-3.5 w-3.5" />
                Back
            </Link>

            <div className="mt-8 flex h-[52px] w-[52px] items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600">
                <LockKeyhole className="h-6 w-6" />
            </div>

            <div className="mt-7">
                <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.045em] text-[#111827]">Reset Password</h1>
                <p className="mt-2 max-w-[360px] text-[13px] font-semibold leading-5 text-[#657792]">
                    Create a new secure password for <span className="font-extrabold text-[#17243a]">{recoveryEmail || supportEmail}</span>.
                </p>
            </div>

            <form
                className="mt-9 space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/admin/password/reset');
                }}
            >
                <AuthField
                    id="new-password"
                    label="New Password"
                    icon={LockKeyhole}
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="new-password"
                    placeholder="••••••••••••"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                    required
                    suffix={
                        <button type="button" className="text-[#8fa0ba]" onClick={() => setShowPassword((value) => !value)} aria-label="Toggle password visibility">
                            {showPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                        </button>
                    }
                />

                <AuthField
                    id="confirm-password"
                    label="Confirm Password"
                    icon={LockKeyhole}
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="new-password"
                    placeholder="••••••••••••"
                    value={form.data.password_confirmation}
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    error={form.errors.password_confirmation}
                    required
                />

                <Button type="submit" className="h-12 w-full rounded-[12px] bg-[#081120] text-[13px] font-extrabold text-white shadow-[0_16px_30px_rgba(8,17,32,0.20)] hover:bg-[#111827]" disabled={form.processing}>
                    {form.processing ? 'Updating password...' : 'Update Password'}
                </Button>
            </form>
        </>
    );
}

export default function Login({ mode = 'login', recoveryEmail = '', authBrand = null }) {
    const title = {
        login: 'Admin Login',
        forgot: 'Recover Access',
        otp: 'Security Check',
        reset: 'Reset Password',
    }[mode] || 'Admin Login';

    return (
        <div className="min-h-screen bg-white font-sans text-[#111827]">
            <Head title={title} />
            <main className="flex min-h-screen items-start justify-center px-6 py-[60px] sm:items-center sm:py-10">
                <section className="relative w-full max-w-[378px]">
                    {mode === 'login' ? <LoginView brand={authBrand} /> : null}
                    {mode === 'forgot' ? <ForgotView brand={authBrand} /> : null}
                    {mode === 'otp' ? <OtpView recoveryEmail={recoveryEmail} brand={authBrand} /> : null}
                    {mode === 'reset' ? <ResetView recoveryEmail={recoveryEmail} brand={authBrand} /> : null}
                    <SecurityFooter brand={authBrand} />
                </section>
            </main>
        </div>
    );
}

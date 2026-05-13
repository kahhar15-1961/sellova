<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminAuthorizer;
use App\Http\Controllers\Controller;
use App\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AdminAuthController extends Controller
{
    private const RECOVERY_SESSION_KEY = 'admin_password_recovery';

    public function create(): Response
    {
        return $this->renderAuth('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], (bool) ($credentials['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user === null || ! AdminAuthorizer::canAccessPanel($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('Your account is not authorized for admin access.'),
            ]);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function forgot(): Response
    {
        return $this->renderAuth('forgot');
    }

    public function sendRecoveryCode(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = Str::lower((string) $payload['email']);
        $code = (string) random_int(100000, 999999);
        $user = StaffUser::query()->where('email', $email)->first();
        $canRecover = $user !== null && AdminAuthorizer::canAccessPanel($user);

        $request->session()->put(self::RECOVERY_SESSION_KEY, [
            'email' => $email,
            'code_hash' => $canRecover ? password_hash($code, PASSWORD_DEFAULT) : null,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'verified' => false,
        ]);

        if ($canRecover) {
            $this->sendRecoveryMail($email, $code);
        }

        return redirect()
            ->route('admin.password.otp')
            ->with('status', 'If this email belongs to an admin account, a verification code has been sent.');
    }

    public function otp(Request $request): Response|RedirectResponse
    {
        $recovery = $this->recoveryState($request);
        if ($recovery === null) {
            return redirect()->route('admin.password.request');
        }

        return $this->renderAuth('otp', [
            'recoveryEmail' => $recovery['email'],
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $recovery = $this->recoveryState($request);
        if ($recovery === null) {
            return redirect()->route('admin.password.request');
        }

        if (! $this->recoveryIsUsable($recovery)) {
            $request->session()->forget(self::RECOVERY_SESSION_KEY);
            throw ValidationException::withMessages([
                'code' => __('This verification code expired. Please request a new code.'),
            ]);
        }

        if (! is_string($recovery['code_hash'] ?? null) || ! password_verify((string) $payload['code'], (string) $recovery['code_hash'])) {
            throw ValidationException::withMessages([
                'code' => __('The verification code is invalid.'),
            ]);
        }

        $recovery['verified'] = true;
        $request->session()->put(self::RECOVERY_SESSION_KEY, $recovery);

        return redirect()->route('admin.password.reset');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $recovery = $this->recoveryState($request);
        if ($recovery === null) {
            return redirect()->route('admin.password.request');
        }

        $email = Str::lower((string) $recovery['email']);
        $code = (string) random_int(100000, 999999);
        $user = StaffUser::query()->where('email', $email)->first();
        $canRecover = $user !== null && AdminAuthorizer::canAccessPanel($user);

        $request->session()->put(self::RECOVERY_SESSION_KEY, [
            'email' => $email,
            'code_hash' => $canRecover ? password_hash($code, PASSWORD_DEFAULT) : null,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'verified' => false,
        ]);

        if ($canRecover) {
            $this->sendRecoveryMail($email, $code);
        }

        return back()->with('status', 'A new verification code has been sent.');
    }

    public function reset(Request $request): Response|RedirectResponse
    {
        $recovery = $this->recoveryState($request);
        if ($recovery === null || ! ($recovery['verified'] ?? false)) {
            return redirect()->route('admin.password.request');
        }

        return $this->renderAuth('reset', [
            'recoveryEmail' => $recovery['email'],
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $recovery = $this->recoveryState($request);
        if ($recovery === null || ! ($recovery['verified'] ?? false)) {
            return redirect()->route('admin.password.request');
        }

        $payload = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ]);

        $user = StaffUser::query()->where('email', Str::lower((string) $recovery['email']))->first();
        if ($user === null || ! AdminAuthorizer::canAccessPanel($user)) {
            $request->session()->forget(self::RECOVERY_SESSION_KEY);

            throw ValidationException::withMessages([
                'password' => __('Unable to reset this account password.'),
            ]);
        }

        $user->forceFill([
            'password_hash' => password_hash((string) $payload['password'], PASSWORD_DEFAULT),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->forget(self::RECOVERY_SESSION_KEY);

        return redirect()->route('admin.login')->with('status', 'Password updated. You can sign in with your new credentials.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function renderAuth(string $mode, array $props = []): Response
    {
        return Inertia::render('Admin/Auth/Login', array_merge([
            'mode' => $mode,
            'header' => [
                'title' => 'Sign in',
                'description' => 'Staff access to the Sellova admin console.',
                'breadcrumbs' => [],
            ],
            'authBrand' => [
                'name' => (string) Config::get('app.name', 'Sellova'),
                'subtitle' => 'Backend System',
                'footer' => (string) Config::get('app.name', 'Sellova').' Escrow',
                'supportEmail' => 'admin@trustcrow.com',
            ],
        ], $props));
    }

    /**
     * @return array{email: string, code_hash?: string|null, expires_at?: string|null, verified?: bool}|null
     */
    private function recoveryState(Request $request): ?array
    {
        $state = $request->session()->get(self::RECOVERY_SESSION_KEY);

        return is_array($state) && isset($state['email']) ? $state : null;
    }

    /**
     * @param array{expires_at?: string|null} $recovery
     */
    private function recoveryIsUsable(array $recovery): bool
    {
        $expiresAt = isset($recovery['expires_at']) ? strtotime((string) $recovery['expires_at']) : false;

        return $expiresAt !== false && $expiresAt >= time();
    }

    private function sendRecoveryMail(string $email, string $code): void
    {
        Mail::raw("Your admin verification code is {$code}. It expires in 10 minutes.", static function ($message) use ($email): void {
            $message->to($email)->subject('Admin password recovery code');
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Commands\Auth\RegisterBuyerCommand;
use App\Domain\Commands\Auth\RegisterSellerCommand;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\StaffUser;
use App\Models\Storefront;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class WebAuthController extends Controller
{
    public function __construct(private readonly AuthService $authService = new AuthService())
    {
    }

    public function login(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user(), (string) $request->query('panel', 'buyer'));
        }

        return Inertia::render('Web/AuthPortal', [
            'mode' => 'login',
            'panel' => $this->normalizePanel((string) $request->query('panel', 'buyer')),
        ]);
    }

    public function register(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user(), (string) $request->query('panel', 'buyer'));
        }

        return Inertia::render('Web/AuthPortal', [
            'mode' => 'register',
            'panel' => $this->normalizePanel((string) $request->query('panel', 'buyer')),
        ]);
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('Web/AuthPortal', [
            'mode' => 'forgot',
            'panel' => 'buyer',
        ]);
    }

    public function storeForgotPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return back()->with('success', 'If that email is registered, password recovery instructions will be sent shortly.');
    }

    public function storeLogin(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'panel' => ['nullable', Rule::in(['buyer', 'seller'])],
        ]);

        $user = StaffUser::query()->where('email', strtolower((string) $payload['email']))->first();
        if ($user === null || ! password_verify((string) $payload['password'], (string) $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => __('This account is not active.'),
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user, true);
        $request->session()->regenerate();

        return $this->redirectForUser($user, $this->normalizePanel((string) ($payload['panel'] ?? 'buyer')));
    }

    public function storeRegister(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'panel' => ['required', Rule::in(['buyer', 'seller'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
            'store_name' => ['nullable', 'string', 'max:140'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $panel = $this->normalizePanel((string) $payload['panel']);
        $email = strtolower((string) $payload['email']);
        $name = trim((string) $payload['name']);
        $country = strtoupper((string) ($payload['country_code'] ?? 'BD'));
        $currency = strtoupper((string) ($payload['currency'] ?? 'BDT'));

        try {
            if ($panel === 'seller') {
                $storeName = trim((string) ($payload['store_name'] ?? $name));
                $result = $this->authService->registerSeller(new RegisterSellerCommand(
                    email: $email,
                    phone: $payload['phone'] ?? null,
                    passwordPlain: (string) $payload['password'],
                    displayName: $storeName !== '' ? $storeName : $name,
                    legalName: trim((string) ($payload['legal_name'] ?? $name)) ?: $name,
                    countryCode: $country,
                    defaultCurrency: $currency,
                ));
                $this->ensureStorefront((int) $result['user_id']);
            } else {
                $result = $this->authService->registerBuyer(new RegisterBuyerCommand(
                    email: $email,
                    phone: $payload['phone'] ?? null,
                    passwordPlain: (string) $payload['password'],
                    displayName: $name,
                    countryCode: $country,
                    defaultCurrency: $currency,
                ));
            }
        } catch (AuthValidationFailedException $exception) {
            throw ValidationException::withMessages([
                'email' => match ($exception->reasonCode) {
                    'email_taken' => __('This email is already registered.'),
                    'phone_taken' => __('This phone number is already registered.'),
                    default => __('Unable to create this account. Please review the details and try again.'),
                },
            ]);
        }

        $user = StaffUser::query()->findOrFail((int) $result['user_id']);
        Auth::login($user, true);
        $request->session()->regenerate();

        return $this->redirectForUser($user, $panel);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.home');
    }

    private function redirectForUser(?object $user, string $panel): RedirectResponse
    {
        if ($panel === 'seller' && $user !== null && $user->sellerProfile()->exists()) {
            return redirect()->intended('/seller/dashboard');
        }

        return redirect()->intended('/dashboard');
    }

    private function normalizePanel(string $panel): string
    {
        return $panel === 'seller' ? 'seller' : 'buyer';
    }

    private function ensureStorefront(int $userId): void
    {
        $seller = SellerProfile::query()->where('user_id', $userId)->first();
        if ($seller === null || Storefront::query()->where('seller_profile_id', $seller->id)->exists()) {
            return;
        }

        $base = Str::slug((string) ($seller->display_name ?: 'seller-'.$seller->id)) ?: 'seller-'.$seller->id;
        $slug = $base;
        $suffix = 2;
        while (Storefront::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        Storefront::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'slug' => $slug,
            'title' => (string) ($seller->display_name ?: 'Seller store'),
            'description' => 'Verified storefront workspace.',
            'policy_text' => null,
            'is_public' => true,
        ]);
    }
}

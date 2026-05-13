<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Commands\Auth\RegisterBuyerCommand;
use App\Domain\Commands\Auth\RegisterSellerCommand;
use App\Domain\Commands\UserSeller\CreateSellerProfileCommand;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Domain\Value\SellerProfileDraft;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StaffUser;
use App\Models\Storefront;
use App\Services\Auth\AuthService;
use App\Services\UserSeller\UserSellerService;
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
    public function __construct(
        private readonly AuthService $authService = new AuthService(),
        private readonly UserSellerService $userSellerService = new UserSellerService(),
    )
    {
    }

    public function login(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user(), $request);
        }

        $panel = $this->resolveAuthPanel($request);
        $request->session()->put('auth.panel', $panel);

        return Inertia::render('Web/AuthPortal', [
            'mode' => 'login',
            'panel' => $panel,
        ]);
    }

    public function register(Request $request): Response|RedirectResponse
    {
        $panel = $this->resolveAuthPanel($request);
        $request->session()->put('auth.panel', $panel);

        if (Auth::check()) {
            $user = Auth::user();
            if ($this->canUpgradeToSeller($user, $panel)) {
                $displayName = $this->preferredUserName($user);
                return Inertia::render('Web/AuthPortal', [
                    'mode' => 'register',
                    'panel' => $panel,
                    'upgrade' => [
                        'enabled' => true,
                        'user' => [
                            'name' => $displayName,
                            'email' => (string) ($user?->email ?? ''),
                            'phone' => (string) ($user?->phone ?? ''),
                            'store_name' => $displayName,
                            'legal_name' => $displayName,
                        ],
                    ],
                ]);
            }

            return $this->redirectForUser($user, $request);
        }

        return Inertia::render('Web/AuthPortal', [
            'mode' => 'register',
            'panel' => $panel,
            'upgrade' => [
                'enabled' => false,
                'user' => null,
            ],
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
        $this->mergeGuestCartIntoBuyerCart($request, (int) $user->id);

        return $this->redirectForUser($user, $request);
    }

    public function storeRegister(Request $request): RedirectResponse
    {
        $panel = $this->normalizePanel((string) $request->input('panel', $this->resolveAuthPanel($request)));
        if (Auth::check() && $panel === 'seller' && Auth::user()?->sellerProfile === null) {
            return $this->storeSellerUpgrade($request);
        }

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
        $this->mergeGuestCartIntoBuyerCart($request, (int) $user->id);

        return $this->redirectForUser($user, $request);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.home');
    }

    private function redirectForUser(?object $user, Request $request): RedirectResponse
    {
        $request->session()->forget('auth.panel');

        return redirect()->intended($this->defaultWorkspacePath($user));
    }

    private function mergeGuestCartIntoBuyerCart(Request $request, int $buyerUserId): void
    {
        $guestCart = $request->session()->get('web_cart', []);
        if (! is_array($guestCart) || $guestCart === []) {
            return;
        }

        $snapshots = $request->session()->get('web_cart_snapshots', []);
        $productIds = array_values(array_filter(array_map('intval', array_keys($guestCart))));
        if ($productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $cart = Cart::query()->firstOrCreate(
            ['buyer_user_id' => $buyerUserId, 'status' => 'active'],
            ['uuid' => (string) Str::uuid(), 'expires_at' => now()->addDays(14)]
        );

        foreach ($guestCart as $productId => $quantity) {
            $productId = (int) $productId;
            $product = $products->get($productId);
            if (! $product instanceof Product) {
                continue;
            }

            $snapshot = is_array($snapshots[$productId] ?? null) ? $snapshots[$productId] : [];
            $item = CartItem::query()->firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
            ]);
            $item->fill([
                'seller_profile_id' => (int) $product->seller_profile_id,
                'quantity' => max(1, (int) ($item->exists ? $item->quantity : 0) + (int) $quantity),
                'unit_price_snapshot' => (string) ($snapshot['price'] ?? $product->base_price),
                'currency_snapshot' => (string) ($snapshot['currency'] ?? $product->currency ?? 'BDT'),
                'metadata_snapshot_json' => [
                    'title' => (string) ($snapshot['title'] ?? $product->title),
                    'image_url' => (string) ($snapshot['image'] ?? $product->image_url ?? ''),
                ],
            ])->save();
        }

        $request->session()->forget('web_cart');
        $request->session()->forget('web_cart_snapshots');
    }

    private function normalizePanel(string $panel): string
    {
        return $panel === 'seller' ? 'seller' : 'buyer';
    }

    private function resolveAuthPanel(Request $request): string
    {
        $intended = (string) $request->session()->get('url.intended', '');
        if (str_starts_with($intended, '/seller')) {
            return 'seller';
        }

        return $this->normalizePanel((string) $request->session()->get('auth.panel', 'buyer'));
    }

    private function canUpgradeToSeller(?object $user, string $panel): bool
    {
        return $panel === 'seller'
            && $user !== null
            && method_exists($user, 'sellerProfile')
            && ! $user->sellerProfile()->exists();
    }

    private function preferredUserName(?object $user): string
    {
        $name = trim((string) ($user?->display_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($user?->email ?? ''));
        if ($email === '') {
            return '';
        }

        return (string) Str::of(strtok($email, '@') ?: $email)
            ->replace(['.', '_', '-'], ' ')
            ->title();
    }

    private function defaultWorkspacePath(?object $user): string
    {
        if ($user !== null && method_exists($user, 'sellerProfile') && $user->sellerProfile()->exists()) {
            return '/seller/dashboard';
        }

        return '/dashboard';
    }

    private function storeSellerUpgrade(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'panel' => ['required', Rule::in(['buyer', 'seller'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'store_name' => ['nullable', 'string', 'max:140'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        /** @var StaffUser|null $user */
        $user = Auth::user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $email = strtolower((string) $payload['email']);
        if ($email !== strtolower((string) $user->email)) {
            throw ValidationException::withMessages([
                'email' => __('Seller onboarding must use your signed-in account email.'),
            ]);
        }

        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone !== '' && StaffUser::query()->where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'phone' => __('This phone number is already registered.'),
            ]);
        }

        $user->display_name = trim((string) $payload['name']);
        $user->phone = $phone !== '' ? $phone : null;
        $user->save();

        $country = strtoupper((string) ($payload['country_code'] ?? 'BD'));
        $currency = strtoupper((string) ($payload['currency'] ?? 'BDT'));
        $storeName = trim((string) ($payload['store_name'] ?? $user->display_name ?? ''));
        $legalName = trim((string) ($payload['legal_name'] ?? $payload['name']));

        $this->userSellerService->createSellerProfile(new CreateSellerProfileCommand(
            userId: (int) $user->id,
            draft: new SellerProfileDraft(
                displayName: $storeName !== '' ? $storeName : ((string) ($user->display_name ?: $user->email)),
                legalName: $legalName !== '' ? $legalName : null,
                countryCode: $country,
                defaultCurrency: $currency,
            ),
        ));

        $this->ensureStorefront((int) $user->id);
        $request->session()->forget('auth.panel');

        return redirect()->intended('/seller/dashboard')->with('success', 'Seller workspace created successfully.');
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

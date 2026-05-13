<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MarketplaceReview;
use App\Models\ProfileViewLog;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Marketplace\BuyerProfileService;
use App\Services\Marketplace\ProfileVisibilityService;
use App\Services\Marketplace\ReviewService;
use App\Services\Marketplace\SellerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class MarketplaceProfileController extends Controller
{
    public function __construct(
        private readonly BuyerProfileService $buyers,
        private readonly SellerProfileService $sellers,
        private readonly ReviewService $reviews,
        private readonly ProfileVisibilityService $visibility,
    ) {
    }

    public function buyerPage(Request $request, User $buyer): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && $this->visibility->canViewBuyer($viewer, $buyer), 403);
        $this->logView($request, 'buyer', (int) $buyer->id, 'web');

        return Inertia::render('Web/TrustProfile', [
            'profileData' => $this->buyers->details($buyer, $viewer, $viewer->isPlatformStaff()),
            'reviewsEndpoint' => '/api/profiles/buyer/'.$buyer->id.'/reviews',
            'canReportReviews' => true,
            'initialMarketplace' => $this->marketplaceShellPayload($viewer),
        ]);
    }

    public function sellerPage(Request $request, SellerProfile $seller): Response
    {
        $viewer = $request->user();
        abort_unless(
            ($viewer instanceof User && $this->visibility->canViewSeller($viewer, $seller))
                || (! ($viewer instanceof User) && (string) $seller->store_status !== 'banned'),
            403,
        );
        $this->logView($request, 'seller', (int) $seller->id, 'web');

        return Inertia::render('Web/TrustProfile', [
            'profileData' => $this->sellers->details($seller, $viewer instanceof User && $viewer->isPlatformStaff()),
            'reviewsEndpoint' => '/api/profiles/seller/'.$seller->id.'/reviews',
            'canReportReviews' => $viewer instanceof User,
            'initialMarketplace' => $this->marketplaceShellPayload($viewer instanceof User ? $viewer : null),
        ]);
    }

    public function buyerForSeller(Request $request, User $buyer): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && $this->visibility->canViewBuyer($viewer, $buyer), 403);
        $this->logView($request, 'buyer', (int) $buyer->id, 'api_seller');

        return response()->json(['ok' => true, 'profile' => $this->buyers->details($buyer, $viewer)]);
    }

    public function sellerForBuyer(Request $request, SellerProfile $seller): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && $this->visibility->canViewSeller($viewer, $seller), 403);
        $this->logView($request, 'seller', (int) $seller->id, 'api_buyer');

        return response()->json(['ok' => true, 'profile' => $this->sellers->details($seller)]);
    }

    public function adminBuyer(Request $request, User $buyer): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && $viewer->isPlatformStaff(), 403);
        $this->logView($request, 'buyer', (int) $buyer->id, 'api_admin');

        return response()->json(['ok' => true, 'profile' => $this->buyers->details($buyer, $viewer, true)]);
    }

    public function adminSeller(Request $request, SellerProfile $seller): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && $viewer->isPlatformStaff(), 403);
        $this->logView($request, 'seller', (int) $seller->id, 'api_admin');

        return response()->json(['ok' => true, 'profile' => $this->sellers->details($seller, true)]);
    }

    public function profileReviews(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless(in_array($type, ['buyer', 'seller'], true), 404);
        $viewer = $request->user();
        if ($type === 'buyer') {
            $buyer = User::query()->findOrFail($id);
            abort_unless($viewer instanceof User && $this->visibility->canViewBuyer($viewer, $buyer), 403);
        } else {
            $seller = SellerProfile::query()->findOrFail($id);
            abort_unless($viewer instanceof User && $this->visibility->canViewSeller($viewer, $seller), 403);
        }

        return response()->json(['ok' => true] + $this->reviews->list($type, $id, $request));
    }

    public function storeReview(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $payload = $request->validate([
            'reviewer_role' => ['required', Rule::in(['buyer', 'seller'])],
            'reviewed_role' => ['required', Rule::in(['buyer', 'seller'])],
            'reviewed_id' => ['required', 'integer', 'min:1'],
            'order_id' => ['required', 'integer', 'min:1'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_type' => ['nullable', Rule::in(['good', 'neutral', 'bad'])],
            'title' => ['nullable', 'string', 'max:160'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'review_images' => ['nullable', 'array', 'max:8'],
            'review_images.*' => ['string', 'max:512'],
            'category_ratings' => ['nullable', 'array'],
        ]);

        $review = $this->reviews->store($actor, $payload);

        return response()->json(['ok' => true, 'review' => $this->reviews->present($review)]);
    }

    public function reportReview(Request $request, MarketplaceReview $review): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $payload = $request->validate([
            'reason_code' => ['required', 'string', 'max:80'],
            'details' => ['nullable', 'string', 'max:1500'],
        ]);

        $report = $this->reviews->report($actor, $review, $payload);

        return response()->json(['ok' => true, 'report' => ['id' => (int) $report->id, 'status' => (string) $report->status]]);
    }

    private function logView(Request $request, string $type, int $id, string $context): void
    {
        ProfileViewLog::query()->create([
            'viewer_id' => Auth::id(),
            'viewer_role' => $request->user()?->sellerProfile ? 'seller' : 'buyer',
            'profile_type' => $type,
            'profile_id' => $id,
            'visibility_context' => $context,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    private function marketplaceShellPayload(?User $user): array
    {
        $user?->loadMissing('sellerProfile');

        return [
            'user' => $user instanceof User ? [
                'id' => (int) $user->id,
                'isAuthenticated' => true,
                'name' => (string) ($user->display_name ?: 'User #'.(int) $user->id),
                'email' => (string) ($user->email ?? ''),
                'role' => $user->sellerProfile === null ? 'buyer' : 'seller',
                'roles' => array_values(array_filter([
                    'buyer',
                    $user->sellerProfile === null ? null : 'seller',
                ])),
                'hasSellerProfile' => $user->sellerProfile !== null,
                'buyerAccountId' => (int) $user->id,
                'sellerAccountId' => $user->sellerProfile?->id ? (int) $user->sellerProfile->id : null,
                'status' => (string) ($user->status ?? 'active'),
                'phone' => (string) ($user->phone ?? ''),
                'avatarUrl' => null,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'city' => (string) ($user->sellerProfile?->city ?? ''),
            ] : [
                'id' => null,
                'isAuthenticated' => false,
                'name' => 'Guest',
                'email' => '',
                'role' => 'guest',
                'roles' => ['guest'],
                'hasSellerProfile' => false,
                'buyerAccountId' => null,
                'sellerAccountId' => null,
                'status' => 'guest',
                'phone' => '',
                'avatarUrl' => null,
                'lastLoginAt' => null,
                'city' => '',
            ],
            'cart' => [],
            'wishlist' => [],
            'buyerOps' => [
                'notifications' => [],
                'unreadNotificationCount' => 0,
            ],
            'categories' => Category::query()
                ->select(['id', 'name', 'parent_id'])
                ->orderBy('parent_id')
                ->orderBy('name')
                ->limit(80)
                ->get()
                ->map(static fn (Category $category): array => [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'parent_id' => $category->parent_id === null ? null : (int) $category->parent_id,
                    'products_count' => 0,
                    'product_count' => 0,
                    'direct_products_count' => 0,
                ])
                ->values()
                ->all(),
            'trustItems' => [
                ['title' => 'Escrow Protection', 'body' => 'Funds stay protected until marketplace delivery is confirmed.'],
                ['title' => 'Verified Profiles', 'body' => 'Seller and buyer trust data is built from marketplace activity.'],
                ['title' => 'Review Controls', 'body' => 'Ratings, good or bad feedback, reports, and moderation support safer decisions.'],
                ['title' => 'Private by Design', 'body' => 'Sensitive email, phone, address, payment, and document data stays hidden.'],
            ],
        ];
    }
}

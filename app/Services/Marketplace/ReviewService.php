<?php

namespace App\Services\Marketplace;

use App\Models\BuyerReview;
use App\Models\MarketplaceReview;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public const SELLER_CATEGORIES = ['product_quality', 'delivery_speed', 'communication', 'accuracy', 'support_behavior'];
    public const BUYER_CATEGORIES = ['payment_reliability', 'communication', 'cooperation', 'dispute_behavior', 'order_completion_behavior'];

    public function list(string $type, int $id, Request $request): array
    {
        $query = MarketplaceReview::query()
            ->with(['reviewer:id,display_name,email,avatar_url', 'ratings'])
            ->where('reviewed_role', $type)
            ->where('reviewed_id', $id)
            ->where('status', 'visible');

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->query('rating'));
        }
        if ($request->filled('feedback_type')) {
            $query->where('feedback_type', (string) $request->query('feedback_type'));
        }

        match ((string) $request->query('sort', 'newest')) {
            'oldest' => $query->oldest('created_at'),
            'highest_rating' => $query->orderByDesc('rating')->latest('id'),
            'lowest_rating' => $query->orderBy('rating')->latest('id'),
            'good_feedback' => $query->orderByRaw("feedback_type = 'good' desc")->latest('id'),
            'bad_feedback' => $query->orderByRaw("feedback_type = 'bad' desc")->latest('id'),
            default => $query->latest('created_at')->latest('id'),
        };

        $page = $query->paginate(min(50, max(1, (int) $request->query('per_page', 12))));

        return [
            'items' => $page->getCollection()->map(fn (MarketplaceReview $review): array => $this->present($review))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }

    public function store(User $actor, array $payload): MarketplaceReview
    {
        $reviewedRole = (string) $payload['reviewed_role'];
        $reviewedId = (int) $payload['reviewed_id'];
        $order = isset($payload['order_id']) ? Order::query()->with('orderItems')->find((int) $payload['order_id']) : null;
        $reviewerRole = (string) $payload['reviewer_role'];

        $this->assertCanReview($actor, $reviewerRole, $reviewedRole, $reviewedId, $order);
        $categories = $this->validatedCategoryRatings($reviewedRole, $payload['category_ratings'] ?? []);

        return DB::transaction(function () use ($actor, $payload, $reviewerRole, $reviewedRole, $reviewedId, $order, $categories): MarketplaceReview {
            $review = MarketplaceReview::query()->updateOrCreate(
                [
                    'reviewer_id' => (int) $actor->id,
                    'reviewed_role' => $reviewedRole,
                    'reviewed_id' => $reviewedId,
                    'order_id' => $order?->id,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'reviewer_role' => $reviewerRole,
                    'rating' => (int) $payload['rating'],
                    'feedback_type' => (string) ($payload['feedback_type'] ?? $this->feedbackFromRating((int) $payload['rating'])),
                    'title' => trim((string) ($payload['title'] ?? '')) ?: null,
                    'comment' => trim((string) ($payload['comment'] ?? '')) ?: null,
                    'tags' => array_values(array_slice(array_filter((array) ($payload['tags'] ?? [])), 0, 12)),
                    'review_images' => array_values(array_slice(array_filter((array) ($payload['review_images'] ?? [])), 0, 8)),
                    'is_verified_order' => $order instanceof Order,
                    'status' => 'visible',
                ],
            );

            $review->ratings()->delete();
            foreach ($categories as $category => $rating) {
                $review->ratings()->create(['category' => $category, 'rating' => $rating]);
            }

            $this->syncLegacyReview($review, $order);

            return $review->fresh(['reviewer', 'ratings']);
        });
    }

    public function report(User $actor, MarketplaceReview $review, array $payload): ReviewReport
    {
        return ReviewReport::query()->updateOrCreate(
            ['marketplace_review_id' => (int) $review->id, 'reporter_id' => (int) $actor->id],
            [
                'reason_code' => (string) $payload['reason_code'],
                'details' => trim((string) ($payload['details'] ?? '')) ?: null,
                'status' => 'open',
            ],
        );
    }

    public function present(MarketplaceReview $review): array
    {
        $reviewerHref = null;
        $reviewerName = ucfirst((string) $review->reviewer_role).' #'.(int) $review->reviewer_id;
        if ((string) $review->reviewer_role === 'seller') {
            $sellerProfile = SellerProfile::query()->where('user_id', (int) $review->reviewer_id)->first(['id', 'display_name']);
            $reviewerHref = $sellerProfile?->id ? '/profiles/sellers/'.(int) $sellerProfile->id : null;
            $reviewerName = (string) ($sellerProfile?->display_name ?: 'Seller #'.(int) ($sellerProfile?->id ?: $review->reviewer_id));
        } elseif ((string) $review->reviewer_role === 'buyer') {
            $reviewerHref = '/profiles/buyers/'.(int) $review->reviewer_id;
            $reviewerName = (string) ($review->reviewer?->display_name ?: 'Buyer #'.(int) $review->reviewer_id);
        }

        return [
            'id' => (int) $review->id,
            'reviewer' => [
                'id' => (int) $review->reviewer_id,
                'name' => $reviewerName,
                'avatar' => $this->publicImage($review->reviewer?->avatar_url),
                'role' => (string) $review->reviewer_role,
                'profile_href' => $reviewerHref,
            ],
            'rating' => (int) $review->rating,
            'feedback_type' => (string) $review->feedback_type,
            'title' => (string) ($review->title ?? ''),
            'comment' => (string) ($review->comment ?? ''),
            'tags' => (array) ($review->tags ?? []),
            'review_images' => (array) ($review->review_images ?? []),
            'is_verified_order' => (bool) $review->is_verified_order,
            'status' => (string) $review->status,
            'category_ratings' => $review->ratings->mapWithKeys(static fn ($rating): array => [(string) $rating->category => (int) $rating->rating])->all(),
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    private function assertCanReview(User $actor, string $reviewerRole, string $reviewedRole, int $reviewedId, ?Order $order): void
    {
        if (! in_array($reviewerRole, ['buyer', 'seller'], true) || ! in_array($reviewedRole, ['buyer', 'seller'], true)) {
            throw ValidationException::withMessages(['reviewed_role' => 'Invalid review direction.']);
        }

        if (! $order instanceof Order) {
            throw ValidationException::withMessages(['order_id' => 'A verified order is required to leave a marketplace review.']);
        }

        $completed = (string) ($order->status instanceof \BackedEnum ? $order->status->value : $order->status) === 'completed'
            || $order->completed_at !== null
            || (string) $order->escrow_status === 'released';
        if (! $completed) {
            throw ValidationException::withMessages(['order_id' => 'Reviews can be submitted only after order completion.']);
        }

        if ($reviewerRole === 'buyer') {
            if ((int) $order->buyer_user_id !== (int) $actor->id || $reviewedRole !== 'seller') {
                throw ValidationException::withMessages(['reviewer_role' => 'Only the buyer can review this seller.']);
            }
            $sellerProfileIds = $order->orderItems->pluck('seller_profile_id')->map(static fn ($id): int => (int) $id)->all();
            if (! in_array($reviewedId, $sellerProfileIds, true)) {
                throw ValidationException::withMessages(['reviewed_id' => 'The seller is not attached to this order.']);
            }
        }

        if ($reviewerRole === 'seller') {
            $seller = $actor->sellerProfile;
            if (! $seller instanceof SellerProfile || $reviewedRole !== 'buyer' || (int) $order->buyer_user_id !== $reviewedId) {
                throw ValidationException::withMessages(['reviewer_role' => 'Only the seller can review this buyer.']);
            }
            $sellerOwnsOrder = (int) $order->seller_user_id === (int) $actor->id
                || $order->orderItems->contains(static fn ($item): bool => (int) $item->seller_profile_id === (int) $seller->id);
            if (! $sellerOwnsOrder) {
                throw ValidationException::withMessages(['order_id' => 'This order is not connected to your seller profile.']);
            }
        }
    }

    private function validatedCategoryRatings(string $reviewedRole, mixed $ratings): array
    {
        $allowed = $reviewedRole === 'seller' ? self::SELLER_CATEGORIES : self::BUYER_CATEGORIES;
        $out = [];
        foreach ((array) $ratings as $category => $rating) {
            if (in_array((string) $category, $allowed, true)) {
                $out[(string) $category] = min(5, max(1, (int) $rating));
            }
        }

        return $out;
    }

    private function syncLegacyReview(MarketplaceReview $review, ?Order $order): void
    {
        if (! $order instanceof Order) {
            return;
        }

        if ($review->reviewed_role === 'seller') {
            $item = $order->orderItems->firstWhere('seller_profile_id', (int) $review->reviewed_id) ?? $order->orderItems->first();
            if ($item instanceof OrderItem) {
                Review::query()->updateOrCreate(
                    ['order_item_id' => (int) $item->id],
                    [
                        'uuid' => (string) Str::uuid(),
                        'buyer_user_id' => (int) $review->reviewer_id,
                        'seller_profile_id' => (int) $review->reviewed_id,
                        'product_id' => (int) ($item->product_id ?? $order->primary_product_id ?? 0),
                        'rating' => (int) $review->rating,
                        'feedback_type' => (string) $review->feedback_type,
                        'title' => $review->title,
                        'comment' => $review->comment,
                        'tags' => $review->tags,
                        'review_images' => $review->review_images,
                        'status' => 'visible',
                    ],
                );
            }
        }

        if ($review->reviewed_role === 'buyer') {
            $seller = User::query()->find((int) $review->reviewer_id)?->sellerProfile;
            if ($seller instanceof SellerProfile) {
                BuyerReview::query()->updateOrCreate(
                    ['order_id' => (int) $order->id, 'seller_profile_id' => (int) $seller->id],
                    [
                        'uuid' => (string) Str::uuid(),
                        'seller_user_id' => (int) $review->reviewer_id,
                        'buyer_user_id' => (int) $review->reviewed_id,
                        'rating' => (int) $review->rating,
                        'feedback_type' => (string) $review->feedback_type,
                        'title' => $review->title,
                        'comment' => $review->comment,
                        'tags' => $review->tags,
                        'status' => 'visible',
                    ],
                );
            }
        }
    }

    private function feedbackFromRating(int $rating): string
    {
        return match (true) {
            $rating >= 4 => 'good',
            $rating <= 2 => 'bad',
            default => 'neutral',
        };
    }

    private function publicImage(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        return str_starts_with($path, 'http') || str_starts_with($path, '/') ? $path : '/'.$path;
    }
}

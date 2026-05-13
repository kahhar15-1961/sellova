<?php

namespace App\Services\Marketplace;

use App\Models\DisputeCase;
use App\Models\MarketplaceReview;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SellerProfile;

class SellerProfileService
{
    public function __construct(private readonly TrustScoreService $trustScores = new TrustScoreService())
    {
    }

    public function details(SellerProfile $seller, bool $admin = false): array
    {
        $seller->loadMissing(['user', 'storefront']);
        $orders = Order::query()->where(function ($query) use ($seller): void {
            $query->where('seller_user_id', (int) $seller->user_id)
                ->orWhereHas('orderItems', static fn ($q) => $q->where('seller_profile_id', (int) $seller->id));
        });
        $totalOrders = (int) (clone $orders)->count();
        $completedOrders = (int) (clone $orders)->where('status', 'completed')->count();
        $cancelledOrders = (int) (clone $orders)->where('status', 'cancelled')->count();
        $disputeCount = (int) DisputeCase::query()->whereHas('order_item', static fn ($q) => $q->where('seller_profile_id', (int) $seller->id))->count();
        $refundCount = (int) ReturnRequest::query()->where('seller_user_id', (int) $seller->user_id)->count();
        $productCount = (int) Product::query()->where('seller_profile_id', (int) $seller->id)->count();
        $legacyReviews = Review::query()
            ->where('seller_profile_id', (int) $seller->id)
            ->where('status', 'visible')
            ->with(['buyer:id,display_name,email,avatar_url', 'product:id,title,image_url,base_price,currency'])
            ->latest('id');
        $marketplaceReviews = MarketplaceReview::query()
            ->where('reviewed_role', 'seller')
            ->where('reviewed_id', (int) $seller->id)
            ->where('status', 'visible')
            ->with(['reviewer:id,display_name,email,avatar_url', 'ratings'])
            ->latest('id');

        $marketplaceReviewRows = (clone $marketplaceReviews)->limit(20)->get();
        $legacyReviewRows = (clone $legacyReviews)->limit(20)->get();
        $ratings = $marketplaceReviewRows->pluck('rating')->merge($legacyReviewRows->pluck('rating'))->map(fn ($v): int => (int) $v);
        $average = $ratings->isEmpty() ? 0 : round((float) $ratings->avg(), 1);
        $feedback = $this->feedbackSummary($marketplaceReviewRows->pluck('feedback_type')->all(), $ratings->all());
        $trust = $this->trustScores->seller($seller);
        $completionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;

        return [
            'type' => 'seller',
            'id' => (int) $seller->id,
            'profile' => [
                'name' => (string) ($seller->display_name ?? $seller->storefront?->title ?? 'Seller #'.$seller->id),
                'avatar' => $this->publicImage($seller->store_logo_url),
                'banner' => $this->publicImage($seller->banner_image_url),
                'verified' => in_array((string) $seller->verification_status, ['verified', 'approved'], true),
                'verification_status' => (string) $seller->verification_status,
                'kyc_status' => (string) ($seller->kyc_status ?? $seller->verification_status ?? 'not_submitted'),
                'store_status' => (string) $seller->store_status,
                'store_created_at' => $seller->created_at?->toIso8601String(),
                'last_active_at' => $seller->last_active_at?->toIso8601String() ?? $seller->user?->last_login_at?->toIso8601String(),
                'description' => (string) ($seller->storefront?->description ?? ''),
                'location' => implode(', ', array_filter([(string) $seller->city, (string) $seller->region, (string) $seller->country])),
            ],
            'stats' => [
                'total_products' => $productCount,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'dispute_count' => $disputeCount,
                'refund_count' => $refundCount,
                'average_rating' => $average,
                'good_feedback_count' => $feedback['good'],
                'neutral_feedback_count' => $feedback['neutral'],
                'bad_feedback_count' => $feedback['bad'],
                'delivery_success_rate' => $completionRate,
                'response_time' => 'Usually within 24 hours',
                'escrow_completion_rate' => $completionRate,
                'product_quality_rating' => $this->categoryAverage($marketplaceReviewRows, 'product_quality', $average),
                'communication_rating' => $this->categoryAverage($marketplaceReviewRows, 'communication', $average),
            ],
            'trust_score' => $trust,
            'store_policies' => $this->storePolicies($seller),
            'featured_products' => Product::query()
                ->where('seller_profile_id', (int) $seller->id)
                ->whereIn('status', ['published', 'active'])
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => (int) $product->id,
                    'title' => (string) $product->title,
                    'image' => $this->publicImage($product->image_url),
                    'price' => (string) $product->base_price,
                    'currency' => (string) ($product->currency ?? $seller->default_currency ?? 'BDT'),
                    'href' => '/products/'.(int) $product->id,
                ])
                ->values()
                ->all(),
            'reviews' => $marketplaceReviewRows->map(fn (MarketplaceReview $review): array => app(ReviewService::class)->present($review))
                ->concat($legacyReviewRows->map(static fn (Review $review): array => [
                    'id' => 'seller-review-'.$review->id,
                    'reviewer' => [
                        'name' => (string) ($review->buyer?->display_name ?: 'Buyer #'.(int) $review->buyer_user_id),
                        'role' => 'buyer',
                        'profile_href' => $review->buyer_user_id ? '/profiles/buyers/'.(int) $review->buyer_user_id : null,
                    ],
                    'rating' => (int) $review->rating,
                    'feedback_type' => (string) ($review->feedback_type ?? ((int) $review->rating >= 4 ? 'good' : ((int) $review->rating <= 2 ? 'bad' : 'neutral'))),
                    'title' => (string) ($review->title ?? ''),
                    'comment' => (string) ($review->comment ?? ''),
                    'tags' => (array) ($review->tags ?? []),
                    'is_verified_order' => true,
                    'seller_reply' => (string) ($review->seller_reply ?? ''),
                    'created_at' => $review->created_at?->toIso8601String(),
                ]))
                ->sortByDesc(static fn (array $row): int => strtotime((string) ($row['created_at'] ?? '')) ?: 0)
                ->values()
                ->take(20)
                ->all(),
            'activity' => $this->activity($seller),
            'privacy' => [
                'email' => $admin ? (string) ($seller->contact_email ?? $seller->user?->email ?? '') : '',
                'phone' => $admin ? (string) ($seller->contact_phone ?? $seller->user?->phone ?? '') : '',
                'private_fields_hidden' => ['full_email', 'full_phone', 'address', 'bank_details', 'government_id', 'private_documents', 'internal_notes'],
            ],
            'actions' => ['contact_allowed' => true],
        ];
    }

    private function activity(SellerProfile $seller): array
    {
        return Order::query()
            ->where('seller_user_id', (int) $seller->user_id)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(static fn (Order $order): array => [
                'type' => 'order',
                'label' => (string) ($order->order_number ?? 'Order #'.$order->id),
                'status' => (string) ($order->status instanceof \BackedEnum ? $order->status->value : $order->status),
                'at' => $order->placed_at?->toIso8601String() ?? $order->created_at?->toIso8601String(),
            ])->values()->all();
    }

    private function storePolicies(SellerProfile $seller): array
    {
        $policies = (array) ($seller->store_policies_json ?? []);
        return $policies !== [] ? $policies : [
            ['label' => 'Delivery', 'value' => (string) ($seller->processing_time_label ?? 'Configured during checkout')],
            ['label' => 'Escrow', 'value' => 'Payment is protected until delivery or resolution.'],
            ['label' => 'Returns', 'value' => 'Return and refund requests follow marketplace review workflow.'],
        ];
    }

    private function feedbackSummary(array $types, array $ratings): array
    {
        $good = count(array_filter($types, static fn ($type): bool => $type === 'good'));
        $neutral = count(array_filter($types, static fn ($type): bool => $type === 'neutral'));
        $bad = count(array_filter($types, static fn ($type): bool => $type === 'bad'));
        if (($good + $neutral + $bad) === 0) {
            foreach ($ratings as $rating) {
                $rating >= 4 ? $good++ : ($rating <= 2 ? $bad++ : $neutral++);
            }
        }

        return compact('good', 'neutral', 'bad');
    }

    private function categoryAverage($reviews, string $category, float $fallback): float
    {
        $values = $reviews->flatMap(fn (MarketplaceReview $review) => $review->ratings)
            ->filter(static fn ($rating): bool => (string) $rating->category === $category)
            ->pluck('rating');

        return $values->isEmpty() ? round($fallback, 1) : round((float) $values->avg(), 1);
    }

    private function publicImage(?string $path): ?string
    {
        $path = trim((string) $path);
        return $path === '' ? null : ((str_starts_with($path, 'http') || str_starts_with($path, '/')) ? $path : '/'.$path);
    }
}

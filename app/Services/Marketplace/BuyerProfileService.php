<?php

namespace App\Services\Marketplace;

use App\Models\BuyerProfile;
use App\Models\BuyerReview;
use App\Models\DisputeCase;
use App\Models\MarketplaceReview;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;

class BuyerProfileService
{
    public function __construct(private readonly TrustScoreService $trustScores = new TrustScoreService())
    {
    }

    public function details(User $buyer, ?User $viewer = null, bool $admin = false): array
    {
        $profile = BuyerProfile::query()->firstOrCreate(
            ['user_id' => (int) $buyer->id],
            [
                'display_name' => (string) ($buyer->display_name ?? 'Buyer #'.$buyer->id),
                'avatar_url' => $buyer->avatar_url,
                'last_active_at' => $buyer->last_login_at,
            ],
        );

        $orders = Order::query()->where('buyer_user_id', (int) $buyer->id);
        $totalOrders = (int) (clone $orders)->count();
        $completedOrders = (int) (clone $orders)->where('status', 'completed')->count();
        $cancelledOrders = (int) (clone $orders)->where('status', 'cancelled')->count();
        $disputeCount = (int) DisputeCase::query()->whereHas('order', static fn ($q) => $q->where('buyer_user_id', (int) $buyer->id))->count();
        $refundCount = (int) ReturnRequest::query()->where('buyer_user_id', (int) $buyer->id)->count();
        $legacyReviews = BuyerReview::query()
            ->where('buyer_user_id', (int) $buyer->id)
            ->where('status', 'visible')
            ->with(['seller_profile', 'order'])
            ->latest('id');
        $marketplaceReviews = MarketplaceReview::query()
            ->where('reviewed_role', 'buyer')
            ->where('reviewed_id', (int) $buyer->id)
            ->where('status', 'visible')
            ->with(['reviewer:id,display_name,email,avatar_url', 'ratings'])
            ->latest('id');

        $marketplaceReviewRows = (clone $marketplaceReviews)->limit(20)->get();
        $legacyReviewRows = (clone $legacyReviews)->limit(20)->get();
        $ratings = $marketplaceReviewRows->pluck('rating')->merge($legacyReviewRows->pluck('rating'))->map(fn ($v): int => (int) $v);
        $average = $ratings->isEmpty() ? 0 : round((float) $ratings->avg(), 1);
        $feedback = $this->feedbackSummary($marketplaceReviewRows->pluck('feedback_type')->all(), $ratings->all());
        $trust = $this->trustScores->buyer($buyer);

        return [
            'type' => 'buyer',
            'id' => (int) $buyer->id,
            'viewer_context' => $admin ? 'admin' : 'seller',
            'profile' => [
                'name' => (string) ($profile->display_name ?? $buyer->display_name ?? 'Buyer #'.$buyer->id),
                'avatar' => $this->publicImage($profile->avatar_url ?? $buyer->avatar_url),
                'verified' => in_array((string) $profile->verification_status, ['verified', 'approved'], true),
                'verification_status' => (string) $profile->verification_status,
                'kyc_status' => (string) $profile->kyc_status,
                'account_created_at' => $buyer->created_at?->toIso8601String(),
                'last_active_at' => $profile->last_active_at?->toIso8601String() ?? $buyer->last_login_at?->toIso8601String(),
                'badges' => (array) ($profile->public_badges_json ?? []),
            ],
            'stats' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'dispute_count' => $disputeCount,
                'refund_count' => $refundCount,
                'average_rating' => $average,
                'good_feedback_count' => $feedback['good'],
                'neutral_feedback_count' => $feedback['neutral'],
                'bad_feedback_count' => $feedback['bad'],
                'order_completion_rate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0,
                'payment_reliability_indicator' => $this->indicator((int) ($profile->payment_reliability_rating ?? 0), $average),
                'communication_rating' => (int) ($profile->communication_rating ?: round($average)),
            ],
            'trust_score' => $trust,
            'reviews' => $marketplaceReviewRows->map(fn (MarketplaceReview $review): array => app(ReviewService::class)->present($review))
                ->concat($legacyReviewRows->map(static fn (BuyerReview $review): array => [
                    'id' => 'buyer-review-'.$review->id,
                    'reviewer' => [
                        'name' => (string) ($review->seller_profile?->display_name ?? 'Seller'),
                        'role' => 'seller',
                        'profile_href' => $review->seller_profile_id ? '/profiles/sellers/'.(int) $review->seller_profile_id : null,
                    ],
                    'rating' => (int) $review->rating,
                    'feedback_type' => (string) ($review->feedback_type ?? ((int) $review->rating >= 4 ? 'good' : ((int) $review->rating <= 2 ? 'bad' : 'neutral'))),
                    'title' => (string) ($review->title ?? ''),
                    'comment' => (string) ($review->comment ?? ''),
                    'tags' => (array) ($review->tags ?? []),
                    'is_verified_order' => true,
                    'created_at' => $review->created_at?->toIso8601String(),
                ]))
                ->sortByDesc(static fn (array $row): int => strtotime((string) ($row['created_at'] ?? '')) ?: 0)
                ->values()
                ->take(20)
                ->all(),
            'activity' => $this->activity($buyer),
            'privacy' => [
                'email' => $admin ? (string) ($buyer->email ?? '') : $this->maskEmail($buyer->email),
                'phone' => $admin ? (string) ($buyer->phone ?? '') : $this->maskPhone($buyer->phone),
                'private_fields_hidden' => ['full_email', 'full_phone', 'address', 'bank_details', 'government_id', 'private_documents', 'internal_notes'],
            ],
            'actions' => [
                'contact_allowed' => $viewer !== null && (new ProfileVisibilityService())->canViewBuyer($viewer, $buyer),
            ],
        ];
    }

    private function activity(User $buyer): array
    {
        return Order::query()
            ->where('buyer_user_id', (int) $buyer->id)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(static fn (Order $order): array => [
                'type' => 'order',
                'label' => (string) ($order->order_number ?? 'Order #'.$order->id),
                'status' => (string) ($order->status instanceof \BackedEnum ? $order->status->value : $order->status),
                'at' => $order->placed_at?->toIso8601String() ?? $order->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
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

    private function indicator(int $rating, float $fallback): string
    {
        $value = $rating > 0 ? $rating : $fallback;
        return match (true) {
            $value >= 4.5 => 'Excellent',
            $value >= 4 => 'Strong',
            $value >= 3 => 'Stable',
            $value > 0 => 'Needs caution',
            default => 'Not enough data',
        };
    }

    private function publicImage(?string $path): ?string
    {
        $path = trim((string) $path);
        return $path === '' ? null : ((str_starts_with($path, 'http') || str_starts_with($path, '/')) ? $path : '/'.$path);
    }

    private function maskEmail(?string $email): string
    {
        $email = (string) $email;
        if (! str_contains($email, '@')) {
            return '';
        }
        [$name, $domain] = explode('@', $email, 2);
        return substr($name, 0, 1).str_repeat('*', max(2, strlen($name) - 1)).'@'.$domain;
    }

    private function maskPhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?? '';
        return $phone === '' ? '' : str_repeat('*', max(0, strlen($phone) - 4)).substr($phone, -4);
    }
}

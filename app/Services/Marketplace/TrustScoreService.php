<?php

namespace App\Services\Marketplace;

use App\Domain\Enums\OrderStatus;
use App\Models\BuyerReview;
use App\Models\DisputeCase;
use App\Models\MarketplaceReview;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\TrustScoreSnapshot;
use App\Models\User;

class TrustScoreService
{
    public function buyer(User $buyer): array
    {
        $orders = Order::query()->where('buyer_user_id', (int) $buyer->id);
        $total = (int) (clone $orders)->count();
        $completed = (int) (clone $orders)->where('status', OrderStatus::Completed->value)->count();
        $cancelled = (int) (clone $orders)->where('status', OrderStatus::Cancelled->value)->count();
        $disputes = (int) DisputeCase::query()->whereHas('order', static fn ($q) => $q->where('buyer_user_id', (int) $buyer->id))->count();
        $refunds = (int) ReturnRequest::query()->where('buyer_user_id', (int) $buyer->id)->count();
        $rating = (float) (BuyerReview::query()->where('buyer_user_id', (int) $buyer->id)->where('status', 'visible')->avg('rating') ?? 0);
        $feedback = $this->feedbackCounts('buyer', (int) $buyer->id, $rating);
        $accountAgeDays = max(0, (int) ($buyer->created_at?->diffInDays(now()) ?? 0));

        return $this->score([
            'completed_orders' => $completed,
            'total_orders' => $total,
            'cancelled_orders' => $cancelled,
            'dispute_count' => $disputes,
            'refund_count' => $refunds,
            'rating_average' => round($rating, 2),
            'good_feedback' => $feedback['good'],
            'bad_feedback' => $feedback['bad'],
            'kyc_verified' => false,
            'account_age_days' => $accountAgeDays,
            'response_time_hours' => null,
            'order_completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ], 'buyer', (int) $buyer->id);
    }

    public function seller(SellerProfile $seller): array
    {
        $orders = Order::query()->where(function ($query) use ($seller): void {
            $query->where('seller_user_id', (int) $seller->user_id)
                ->orWhereHas('orderItems', static fn ($q) => $q->where('seller_profile_id', (int) $seller->id));
        });
        $total = (int) (clone $orders)->count();
        $completed = (int) (clone $orders)->where('status', OrderStatus::Completed->value)->count();
        $cancelled = (int) (clone $orders)->where('status', OrderStatus::Cancelled->value)->count();
        $disputes = (int) DisputeCase::query()->whereHas('order_item', static fn ($q) => $q->where('seller_profile_id', (int) $seller->id))->count();
        $refunds = (int) ReturnRequest::query()->where('seller_user_id', (int) $seller->user_id)->count();
        $rating = (float) (Review::query()->where('seller_profile_id', (int) $seller->id)->where('status', 'visible')->avg('rating') ?? 0);
        $feedback = $this->feedbackCounts('seller', (int) $seller->id, $rating);
        $accountAgeDays = max(0, (int) ($seller->created_at?->diffInDays(now()) ?? 0));

        return $this->score([
            'completed_orders' => $completed,
            'total_orders' => $total,
            'cancelled_orders' => $cancelled,
            'dispute_count' => $disputes,
            'refund_count' => $refunds,
            'rating_average' => round($rating, 2),
            'good_feedback' => $feedback['good'],
            'bad_feedback' => $feedback['bad'],
            'kyc_verified' => in_array((string) $seller->verification_status, ['verified', 'approved'], true),
            'account_age_days' => $accountAgeDays,
            'response_time_hours' => null,
            'order_completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ], 'seller', (int) $seller->id);
    }

    private function score(array $factors, string $type, int $id): array
    {
        if ((int) $factors['total_orders'] === 0 && (int) $factors['account_age_days'] < 14) {
            $score = 45;
            $label = 'New user';
        } else {
            $completion = (float) $factors['order_completion_rate'];
            $rating = ((float) $factors['rating_average'] / 5) * 100;
            $disputePenalty = min(30, ((int) $factors['dispute_count'] / max(1, (int) $factors['total_orders'])) * 100);
            $refundPenalty = min(20, ((int) $factors['refund_count'] / max(1, (int) $factors['total_orders'])) * 70);
            $feedbackRatio = (((int) $factors['good_feedback'] + 1) / max(1, ((int) $factors['bad_feedback'] + 1))) * 15;
            $ageBonus = min(8, ((int) $factors['account_age_days']) / 45);
            $kycBonus = $factors['kyc_verified'] ? 8 : 0;
            $score = (int) round(max(0, min(100, ($completion * 0.34) + ($rating * 0.32) + $feedbackRatio + $ageBonus + $kycBonus - $disputePenalty - $refundPenalty)));
            $label = match (true) {
                $score >= 85 => 'Excellent',
                $score >= 70 => 'Good',
                $score >= 50 => 'Average',
                default => 'Risky',
            };
        }

        TrustScoreSnapshot::query()->create([
            'profile_type' => $type,
            'profile_id' => $id,
            'score' => $score,
            'label' => $label,
            'factors_json' => $factors,
            'calculated_at' => now(),
        ]);

        return ['score' => $score, 'label' => $label, 'factors' => $factors];
    }

    private function feedbackCounts(string $role, int $id, float $fallbackRating): array
    {
        $reviews = MarketplaceReview::query()
            ->where('reviewed_role', $role)
            ->where('reviewed_id', $id)
            ->where('status', 'visible');

        $good = (int) (clone $reviews)->where('feedback_type', 'good')->count();
        $bad = (int) (clone $reviews)->where('feedback_type', 'bad')->count();
        $neutral = (int) (clone $reviews)->where('feedback_type', 'neutral')->count();

        if (($good + $bad + $neutral) === 0 && $fallbackRating > 0) {
            $good = $fallbackRating >= 4 ? 1 : 0;
            $bad = $fallbackRating <= 2 ? 1 : 0;
            $neutral = $good === 0 && $bad === 0 ? 1 : 0;
        }

        return ['good' => $good, 'neutral' => $neutral, 'bad' => $bad];
    }
}

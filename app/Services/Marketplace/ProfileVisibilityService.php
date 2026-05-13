<?php

namespace App\Services\Marketplace;

use App\Models\ChatThread;
use App\Models\BuyerReview;
use App\Models\DisputeCase;
use App\Models\MarketplaceReview;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReturnRequest;
use App\Models\SellerProfile;
use App\Models\User;

class ProfileVisibilityService
{
    public function canViewSeller(User $viewer, SellerProfile $seller): bool
    {
        return $viewer->isPlatformStaff()
            || (int) $viewer->id === (int) $seller->user_id
            || (string) $seller->store_status !== 'banned';
    }

    public function canViewBuyer(User $viewer, User $buyer): bool
    {
        if ($viewer->isPlatformStaff() || (int) $viewer->id === (int) $buyer->id) {
            return true;
        }

        $seller = $viewer->sellerProfile;
        if (! $seller instanceof SellerProfile) {
            return false;
        }

        return $this->sellerHasBuyerInteraction((int) $seller->id, (int) $seller->user_id, (int) $buyer->id);
    }

    public function sellerHasBuyerInteraction(int $sellerProfileId, int $sellerUserId, int $buyerUserId): bool
    {
        if (Order::query()->where('buyer_user_id', $buyerUserId)->where('seller_user_id', $sellerUserId)->exists()) {
            return true;
        }

        if (Order::query()
            ->where('buyer_user_id', $buyerUserId)
            ->whereHas('orderItems', static fn ($query) => $query->where('seller_profile_id', $sellerProfileId))
            ->exists()) {
            return true;
        }

        if (ChatThread::query()->where('buyer_user_id', $buyerUserId)->where('seller_user_id', $sellerUserId)->exists()) {
            return true;
        }

        if (ReturnRequest::query()->where('buyer_user_id', $buyerUserId)->where('seller_user_id', $sellerUserId)->exists()) {
            return true;
        }

        if (Review::query()->where('buyer_user_id', $buyerUserId)->where('seller_profile_id', $sellerProfileId)->exists()) {
            return true;
        }

        if (BuyerReview::query()->where('buyer_user_id', $buyerUserId)->where('seller_profile_id', $sellerProfileId)->exists()) {
            return true;
        }

        if (MarketplaceReview::query()
            ->where('reviewed_role', 'buyer')
            ->where('reviewed_id', $buyerUserId)
            ->where('reviewer_role', 'seller')
            ->where('reviewer_id', $sellerUserId)
            ->exists()) {
            return true;
        }

        if (MarketplaceReview::query()
            ->where('reviewer_role', 'buyer')
            ->where('reviewer_id', $buyerUserId)
            ->where('reviewed_role', 'seller')
            ->where('reviewed_id', $sellerProfileId)
            ->exists()) {
            return true;
        }

        return DisputeCase::query()
            ->whereHas('order', static fn ($query) => $query->where('buyer_user_id', $buyerUserId))
            ->whereHas('order_item', static fn ($query) => $query->where('seller_profile_id', $sellerProfileId))
            ->exists();
    }
}

<?php

namespace App\Auth;

use App\Models\Order;
use App\Models\User;

/**
 * Resolves buyer vs seller participants for a single order (multi-seller orders supported at row level).
 */
final class OrderParticipant
{
    /**
     * @return list<int>
     */
    public static function sellerUserIds(Order $order): array
    {
        if ((int) ($order->seller_user_id ?? 0) > 0) {
            return [(int) $order->seller_user_id];
        }

        $order->loadMissing('orderItems.seller_profile');

        return $order->orderItems
            ->map(static fn ($item) => $item->seller_profile !== null ? (int) $item->seller_profile->user_id : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function isBuyer(User $user, Order $order): bool
    {
        return (int) $order->buyer_user_id === (int) $user->id;
    }

    public static function isSellerParticipant(User $user, Order $order): bool
    {
        return (int) ($order->seller_user_id ?? 0) === (int) $user->id
            || in_array((int) $user->id, self::sellerUserIds($order), true);
    }

    public static function isParticipant(User $user, Order $order): bool
    {
        return self::isBuyer($user, $order) || self::isSellerParticipant($user, $order);
    }

    private function __construct()
    {
    }
}

<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OrderVisibilityService
{
    public const MODE_BUYER = 'buyer';
    public const MODE_SELLER = 'seller';
    public const MODE_ADMIN = 'admin';

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function apply(Builder $query, User $actor, string $mode): Builder
    {
        if ($mode === self::MODE_ADMIN) {
            return $actor->isPlatformStaff() ? $query : $query->whereRaw('1 = 0');
        }

        if ($mode === self::MODE_SELLER) {
            return $query->where('seller_user_id', $actor->id);
        }

        return $query->where('buyer_user_id', $actor->id);
    }

    public function canView(User $actor, Order $order, string $mode = self::MODE_BUYER): bool
    {
        if ($actor->isPlatformStaff()) {
            return true;
        }

        return match ($mode) {
            self::MODE_SELLER => (int) $order->seller_user_id === (int) $actor->id,
            default => (int) $order->buyer_user_id === (int) $actor->id,
        };
    }
}


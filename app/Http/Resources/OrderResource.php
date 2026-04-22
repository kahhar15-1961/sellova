<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;

final class OrderResource
{
    /**
     * @return array<string, mixed>
     */
    public static function detail(Order $order): array
    {
        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'buyer_user_id' => $order->buyer_user_id,
            'status' => $order->status->value,
            'currency' => $order->currency,
            'gross_amount' => (string) $order->gross_amount,
            'discount_amount' => (string) $order->discount_amount,
            'fee_amount' => (string) $order->fee_amount,
            'net_amount' => (string) $order->net_amount,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}

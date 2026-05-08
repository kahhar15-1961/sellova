<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Services\TimeoutAutomation\TimeoutAutomationService;

final class OrderResource
{
    /**
     * @return array<string, mixed>
     */
    public static function detail(Order $order): array
    {
        $order->loadMissing(['orderItems', 'escrowAccount', 'paymentIntents.paymentTransactions', 'paymentTransactions.payment_intent']);
        $latestIntent = $order->paymentIntents->sortByDesc('id')->first();
        $latestTxn = $order->paymentTransactions->sortByDesc('id')->first();
        $paymentMethod = null;
        if ($latestTxn !== null) {
            $paymentMethod = $latestTxn->raw_payload_json['method'] ?? $latestTxn->raw_payload_json['payment_method'] ?? null;
        }
        if ($paymentMethod === null && $latestIntent !== null) {
            $paymentMethod = $latestIntent->provider;
        }
        $timerState = (new TimeoutAutomationService())->timerState($order);

        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'buyer_user_id' => $order->buyer_user_id,
            'seller_user_id' => $order->seller_user_id,
            'primary_product_id' => $order->primary_product_id,
            'product_type' => $order->product_type,
            'status' => $order->status->value,
            'fulfillment_state' => $order->fulfillment_state,
            'payment_status' => $order->status->value,
            'escrow_state' => $order->escrowAccount?->state->value ?? 'unavailable',
            'currency' => $order->currency,
            'gross_amount' => (string) $order->gross_amount,
            'discount_amount' => (string) $order->discount_amount,
            'fee_amount' => (string) $order->fee_amount,
            'shipping_amount' => (string) $order->fee_amount,
            'net_amount' => (string) $order->net_amount,
            'total_amount' => (string) $order->net_amount,
            'payment_method' => $paymentMethod,
            'payment_provider' => $latestIntent?->provider,
            'promo_code' => $order->promo_code,
            'shipping_method' => $order->shipping_method,
            'shipping_address_id' => $order->shipping_address_id,
            'shipping_recipient_name' => $order->shipping_recipient_name,
            'shipping_address_line' => $order->shipping_address_line,
            'shipping_phone' => $order->shipping_phone,
            'courier_company' => $order->courier_company,
            'tracking_id' => $order->tracking_id,
            'tracking_url' => $order->tracking_url,
            'shipping_note' => $order->shipping_note,
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'delivery_submitted_at' => $order->delivery_submitted_at?->toIso8601String(),
            'buyer_review_started_at' => $order->buyer_review_started_at?->toIso8601String(),
            'release_eligible_at' => $order->release_eligible_at?->toIso8601String(),
            'placed_at' => $order->placed_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'cancel_reason' => $order->cancel_reason,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'can_cancel' => in_array($order->status->value, ['pending_payment', 'paid', 'paid_in_escrow', 'escrow_funded'], true),
            'cancel_policy' => [
                'allowed_until_status' => 'processing',
                'message' => 'Orders can be cancelled until the seller starts processing.',
            ],
            'timeout_state' => $timerState,
            'items' => $order->orderItems->map(static fn ($item): array => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_type' => $item->product_type_snapshot,
                'seller_profile_id' => $item->seller_profile_id,
                'title' => $item->title_snapshot,
                'sku' => $item->sku_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price_snapshot,
                'line_total' => (string) $item->line_total_snapshot,
                'delivery_state' => $item->delivery_state,
            ])->values()->all(),
        ];
    }
}

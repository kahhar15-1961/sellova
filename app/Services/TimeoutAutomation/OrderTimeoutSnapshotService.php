<?php

namespace App\Services\TimeoutAutomation;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;

final class OrderTimeoutSnapshotService
{
    public function __construct(private readonly EscrowTimeoutSettingsService $settings = new EscrowTimeoutSettingsService())
    {
    }

    public function snapshotAtOrderCreation(Order $order, ?Product $product = null): void
    {
        $settings = $this->settings->current();
        $policy = $this->basePolicy($settings, $product);
        $now = now();
        $order->expires_at = $now->copy()->addMinutes((int) $policy['unpaid_order_expiration_minutes']);
        $order->unpaid_reminder_at = $this->beforeOrNull($order->expires_at, (int) $policy['unpaid_order_warning_minutes'], 'minutes', $now);
        $order->timeout_policy_snapshot_json = $policy;
        $order->save();
    }

    public function snapshotAtEscrowFunded(Order $order, ?Product $product = null): void
    {
        $policy = $this->policyForOrder($order, $product);
        $now = now();
        $order->seller_deadline_at = $now->copy()->addHours((int) $policy['seller_fulfillment_deadline_hours']);
        $order->seller_reminder_at = $this->beforeOrNull($order->seller_deadline_at, (int) $policy['seller_fulfillment_warning_hours'], 'hours', $now);
        $order->timeout_policy_snapshot_json = $policy;
        $order->save();
    }

    public function snapshotAtDeliverySubmitted(Order $order, ?Product $product = null): void
    {
        $policy = $this->policyForOrder($order, $product);
        $start = $order->buyer_review_started_at instanceof Carbon ? $order->buyer_review_started_at : now();
        $deadlineHours = (int) $policy['buyer_review_deadline_hours'];
        $order->buyer_review_started_at = $start;
        $order->buyer_review_expires_at = $start->copy()->addHours($deadlineHours);
        $order->reminder_1_at = $start->copy()->addHours(min((int) $policy['buyer_review_reminder_1_hours'], max(1, $deadlineHours - 1)));
        $order->reminder_2_at = $start->copy()->addHours(min((int) $policy['buyer_review_reminder_2_hours'], max(1, $deadlineHours - 1)));
        $order->escalation_at = $order->buyer_review_expires_at;
        $order->escalation_warning_at = $this->beforeOrNull($order->escalation_at, (int) $policy['escalation_warning_minutes'], 'minutes', $start);
        $order->auto_release_at = ((bool) $policy['auto_release_after_buyer_timeout']) ? $order->buyer_review_expires_at : null;
        $order->release_eligible_at = $order->buyer_review_expires_at;
        $order->timeout_policy_snapshot_json = $policy;
        $order->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function policyForOrder(Order $order, ?Product $product): array
    {
        $snapshot = is_array($order->timeout_policy_snapshot_json) ? $order->timeout_policy_snapshot_json : [];
        return array_replace($this->basePolicy($this->settings->current(), $product), $snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePolicy(\App\Models\EscrowTimeoutSetting $settings, ?Product $product): array
    {
        $attributes = is_array($product?->attributes_json) ? $product->attributes_json : [];

        $sellerFulfillment = $this->boundedHours(
            $attributes['delivery_fulfillment_hours'] ?? null,
            (int) $settings->seller_fulfillment_deadline_hours,
            (int) $settings->seller_min_fulfillment_hours,
            (int) $settings->seller_max_fulfillment_hours,
        );
        $buyerReview = $this->boundedHours(
            $attributes['buyer_confirmation_hours'] ?? null,
            (int) $settings->buyer_review_deadline_hours,
            (int) $settings->buyer_min_review_hours,
            (int) $settings->buyer_max_review_hours,
        );

        return [
            'unpaid_order_expiration_minutes' => (int) $settings->unpaid_order_expiration_minutes,
            'unpaid_order_warning_minutes' => min((int) ($settings->unpaid_order_warning_minutes ?? 10), max(1, (int) $settings->unpaid_order_expiration_minutes - 1)),
            'seller_fulfillment_deadline_hours' => $sellerFulfillment,
            'seller_fulfillment_warning_hours' => min((int) ($settings->seller_fulfillment_warning_hours ?? 2), max(1, $sellerFulfillment - 1)),
            'buyer_review_deadline_hours' => $buyerReview,
            'buyer_review_reminder_1_hours' => min((int) $settings->buyer_review_reminder_1_hours, max(1, $buyerReview - 1)),
            'buyer_review_reminder_2_hours' => min((int) $settings->buyer_review_reminder_2_hours, max(1, $buyerReview - 1)),
            'escalation_warning_minutes' => min((int) ($settings->escalation_warning_minutes ?? 60), max(1, ($buyerReview * 60) - 1)),
            'auto_escalation_after_review_expiry' => (bool) $settings->auto_escalation_after_review_expiry,
            'auto_cancel_unpaid_orders' => (bool) $settings->auto_cancel_unpaid_orders,
            'auto_release_after_buyer_timeout' => (bool) $settings->auto_release_after_buyer_timeout,
            'auto_create_dispute_on_timeout' => (bool) $settings->auto_create_dispute_on_timeout,
            'dispute_review_queue_enabled' => (bool) $settings->dispute_review_queue_enabled,
            'seller_overrides' => [
                'delivery_fulfillment_hours' => $attributes['delivery_fulfillment_hours'] ?? null,
                'buyer_confirmation_hours' => $attributes['buyer_confirmation_hours'] ?? null,
                'delivery_validity_hours' => $attributes['delivery_validity_hours'] ?? null,
                'instant_delivery_expiration_hours' => $attributes['instant_delivery_expiration_hours'] ?? null,
                'digital_access_validity_hours' => $attributes['digital_access_validity_hours'] ?? null,
            ],
        ];
    }

    private function boundedHours(mixed $raw, int $default, int $min, int $max): int
    {
        if ($raw === null || $raw === '') {
            return max($min, min($max, $default));
        }

        return max($min, min($max, (int) $raw));
    }

    private function beforeOrNull(Carbon $deadline, int $amount, string $unit, Carbon $floor): ?Carbon
    {
        $candidate = $unit === 'hours'
            ? $deadline->copy()->subHours($amount)
            : $deadline->copy()->subMinutes($amount);

        return $candidate->greaterThan($floor) ? $candidate : null;
    }
}

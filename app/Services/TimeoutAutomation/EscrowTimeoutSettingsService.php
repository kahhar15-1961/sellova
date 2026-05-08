<?php

namespace App\Services\TimeoutAutomation;

use App\Models\EscrowTimeoutSetting;
use App\Services\Audit\AuditService;

final class EscrowTimeoutSettingsService
{
    public function current(): EscrowTimeoutSetting
    {
        $settings = EscrowTimeoutSetting::query()->first();
        if ($settings !== null) {
            return $settings;
        }

        return EscrowTimeoutSetting::query()->create([]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(array $payload, ?int $actorUserId = null): EscrowTimeoutSetting
    {
        $settings = $this->current();
        $before = $settings->toArray();
        $settings->fill($this->sanitize($payload));
        $settings->updated_by_user_id = $actorUserId;
        $settings->save();

        (new AuditService())->record(
            actorId: $actorUserId,
            actorRole: 'admin',
            action: 'escrow_timeout.settings_updated',
            targetType: 'escrow_timeout_settings',
            targetId: (int) $settings->id,
            before: $before,
            after: $settings->fresh()?->toArray() ?? $settings->toArray(),
            reasonCode: 'admin_update',
        );

        return $settings->fresh() ?? $settings;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitize(array $payload): array
    {
        $numericBounds = [
            'unpaid_order_expiration_minutes' => [5, 10080],
            'unpaid_order_warning_minutes' => [1, 10080],
            'seller_fulfillment_deadline_hours' => [1, 720],
            'seller_fulfillment_warning_hours' => [1, 720],
            'buyer_review_deadline_hours' => [1, 720],
            'buyer_review_reminder_1_hours' => [1, 720],
            'buyer_review_reminder_2_hours' => [1, 720],
            'escalation_warning_minutes' => [1, 10080],
            'seller_min_fulfillment_hours' => [1, 720],
            'seller_max_fulfillment_hours' => [1, 720],
            'buyer_min_review_hours' => [1, 720],
            'buyer_max_review_hours' => [1, 720],
        ];

        $data = [];
        foreach ($numericBounds as $key => [$min, $max]) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = max($min, min($max, (int) $payload[$key]));
            }
        }

        foreach ([
            'auto_escalation_after_review_expiry',
            'auto_cancel_unpaid_orders',
            'auto_release_after_buyer_timeout',
            'auto_create_dispute_on_timeout',
            'dispute_review_queue_enabled',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = filter_var($payload[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (isset($data['seller_min_fulfillment_hours'], $data['seller_max_fulfillment_hours'])
            && $data['seller_min_fulfillment_hours'] > $data['seller_max_fulfillment_hours']) {
            $data['seller_max_fulfillment_hours'] = $data['seller_min_fulfillment_hours'];
        }

        if (isset($data['unpaid_order_expiration_minutes'], $data['unpaid_order_warning_minutes'])
            && $data['unpaid_order_warning_minutes'] >= $data['unpaid_order_expiration_minutes']) {
            $data['unpaid_order_warning_minutes'] = max(1, $data['unpaid_order_expiration_minutes'] - 1);
        }

        if (isset($data['seller_fulfillment_deadline_hours'], $data['seller_fulfillment_warning_hours'])
            && $data['seller_fulfillment_warning_hours'] >= $data['seller_fulfillment_deadline_hours']) {
            $data['seller_fulfillment_warning_hours'] = max(1, $data['seller_fulfillment_deadline_hours'] - 1);
        }

        if (isset($data['buyer_min_review_hours'], $data['buyer_max_review_hours'])
            && $data['buyer_min_review_hours'] > $data['buyer_max_review_hours']) {
            $data['buyer_max_review_hours'] = $data['buyer_min_review_hours'];
        }

        if (isset($data['buyer_review_deadline_hours'], $data['buyer_review_reminder_2_hours'])
            && $data['buyer_review_reminder_2_hours'] >= $data['buyer_review_deadline_hours']) {
            $data['buyer_review_reminder_2_hours'] = max(1, $data['buyer_review_deadline_hours'] - 1);
        }

        if (isset($data['buyer_review_reminder_1_hours'], $data['buyer_review_reminder_2_hours'])
            && $data['buyer_review_reminder_1_hours'] >= $data['buyer_review_reminder_2_hours']) {
            $data['buyer_review_reminder_1_hours'] = max(1, $data['buyer_review_reminder_2_hours'] - 1);
        }

        return $data;
    }
}

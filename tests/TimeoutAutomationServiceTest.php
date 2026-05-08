<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Enums\EscrowState;
use App\Domain\Enums\OrderStatus;
use App\Models\EscrowAccount;
use App\Models\EscrowTimeoutEvent;
use App\Models\EscrowTimeoutSetting;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\TimeoutAutomation\TimeoutAutomationService;
use Illuminate\Support\Str;

final class TimeoutAutomationServiceTest extends TestCase
{
    public function test_unpaid_expiration_auto_cancels_order_and_logs_notifications(): void
    {
        [$buyer, , $order] = $this->seedTimeoutOrder(OrderStatus::PendingPayment);
        $order->forceFill(['expires_at' => now()->subMinute()])->save();

        EscrowTimeoutSetting::query()->create(['auto_cancel_unpaid_orders' => true]);

        $result = (new TimeoutAutomationService())->processDue();

        self::assertSame(1, $result['unpaid_cancelled']);
        self::assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        self::assertTrue(EscrowTimeoutEvent::query()->where('order_id', $order->id)->where('event_type', 'unpaid_order_cancelled')->exists());
        self::assertTrue(Notification::query()->where('user_id', $buyer->id)->where('template_code', 'escrow.timeout.unpaid_cancelled')->exists());
    }

    public function test_timeout_warnings_are_processed_once_before_expiry_actions(): void
    {
        [$buyer, , $order] = $this->seedTimeoutOrder(OrderStatus::PendingPayment);
        $order->forceFill([
            'expires_at' => now()->addMinutes(5),
            'unpaid_reminder_at' => now()->subMinute(),
        ])->save();

        $result = (new TimeoutAutomationService())->processDue();
        $repeat = (new TimeoutAutomationService())->processDue();

        self::assertSame(1, $result['unpaid_warnings_sent']);
        self::assertSame(0, $repeat['unpaid_warnings_sent']);
        self::assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        self::assertTrue(EscrowTimeoutEvent::query()->where('order_id', $order->id)->where('event_type', 'unpaid_order_expiration_warning')->exists());
        self::assertTrue(Notification::query()->where('user_id', $buyer->id)->where('template_code', 'escrow.timeout.unpaid_warning')->exists());
    }

    public function test_seller_deadline_warning_is_dispute_safe_and_idempotent(): void
    {
        [, $seller, $order] = $this->seedTimeoutOrder(OrderStatus::EscrowFunded);
        $order->forceFill([
            'fulfillment_state' => 'awaiting_seller',
            'seller_reminder_at' => now()->subMinute(),
            'seller_deadline_at' => now()->addHour(),
        ])->save();

        $result = (new TimeoutAutomationService())->processDue();
        $repeat = (new TimeoutAutomationService())->processDue();

        self::assertSame(1, $result['seller_deadline_warnings_sent']);
        self::assertSame(0, $repeat['seller_deadline_warnings_sent']);
        self::assertTrue(EscrowTimeoutEvent::query()->where('order_id', $order->id)->where('event_type', 'seller_fulfillment_deadline_warning')->exists());
        self::assertTrue(Notification::query()->where('user_id', $seller->user_id)->where('template_code', 'escrow.timeout.seller_deadline_warning')->exists());
    }

    public function test_buyer_review_timeout_can_create_dispute_and_interrupt_release(): void
    {
        [$buyer, , $order] = $this->seedTimeoutOrder(OrderStatus::BuyerReview);
        $order->forceFill([
            'buyer_review_started_at' => now()->subHours(4),
            'buyer_review_expires_at' => now()->subMinute(),
            'escalation_at' => now()->subMinute(),
            'timeout_policy_snapshot_json' => [
                'auto_create_dispute_on_timeout' => true,
                'auto_release_after_buyer_timeout' => false,
                'auto_escalation_after_review_expiry' => true,
            ],
        ])->save();

        EscrowAccount::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'state' => EscrowState::Held,
            'currency' => 'USD',
            'held_amount' => '15.0000',
            'released_amount' => '0.0000',
            'refunded_amount' => '0.0000',
            'version' => 1,
            'held_at' => now(),
        ]);

        $result = (new TimeoutAutomationService())->processDue();

        self::assertSame(1, $result['buyer_review_expired']);
        self::assertSame(1, $result['auto_disputed']);
        self::assertSame(OrderStatus::Disputed, $order->fresh()->status);
        self::assertTrue($order->disputeCases()->where('opened_by_user_id', $buyer->id)->exists());
        self::assertTrue(EscrowTimeoutEvent::query()->where('order_id', $order->id)->where('event_type', 'buyer_review_timeout_dispute_created')->exists());
    }

    public function test_buyer_review_escalation_warning_is_sent_before_review_expiry(): void
    {
        [$buyer, $seller, $order] = $this->seedTimeoutOrder(OrderStatus::BuyerReview);
        $order->forceFill([
            'buyer_review_started_at' => now()->subHours(2),
            'buyer_review_expires_at' => now()->addHour(),
            'reminder_1_at' => now()->subHour(),
            'reminder_2_at' => now()->subMinutes(30),
            'escalation_warning_at' => now()->subMinute(),
            'escalation_at' => now()->addHour(),
        ])->save();

        EscrowTimeoutEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'event_type' => 'buyer_review_reminder_1',
            'status' => 'processed',
            'action_taken' => 'reminder_sent',
            'scheduled_for' => now()->subHour(),
            'processed_at' => now()->subHour(),
        ]);
        EscrowTimeoutEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'event_type' => 'buyer_review_reminder_2',
            'status' => 'processed',
            'action_taken' => 'reminder_sent',
            'scheduled_for' => now()->subMinutes(30),
            'processed_at' => now()->subMinutes(30),
        ]);

        $result = (new TimeoutAutomationService())->processDue();
        $state = (new TimeoutAutomationService())->timerState($order->fresh());

        self::assertSame(1, $result['escalation_warnings_sent']);
        self::assertSame('buyer_review_expiry', $state['expiry_action']);
        self::assertTrue(Notification::query()->where('user_id', $buyer->id)->where('template_code', 'escrow.timeout.escalation_warning')->exists());
        self::assertTrue(Notification::query()->where('user_id', $seller->user_id)->where('template_code', 'escrow.timeout.escalation_warning_seller')->exists());
    }

    /**
     * @return array{0: User, 1: SellerProfile, 2: Order}
     */
    private function seedTimeoutOrder(OrderStatus $status): array
    {
        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'timeout-buyer-'.Str::random(8).'@example.test',
            'password_hash' => 'x',
            'status' => 'active',
            'risk_level' => 'low',
        ]);
        $sellerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'timeout-seller-'.Str::random(8).'@example.test',
            'password_hash' => 'x',
            'status' => 'active',
            'risk_level' => 'low',
        ]);
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Timeout Seller',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'TO-'.Str::upper(Str::random(8)),
            'buyer_user_id' => $buyer->id,
            'seller_user_id' => $sellerUser->id,
            'product_type' => 'digital',
            'fulfillment_state' => 'buyer_review',
            'status' => $status,
            'currency' => 'USD',
            'gross_amount' => '15.0000',
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => '15.0000',
            'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'seller_profile_id' => $seller->id,
            'product_type_snapshot' => 'digital',
            'title_snapshot' => 'Digital item',
            'quantity' => 1,
            'unit_price_snapshot' => '15.0000',
            'line_total_snapshot' => '15.0000',
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'in_progress',
        ]);

        return [$buyer, $seller, $order];
    }
}

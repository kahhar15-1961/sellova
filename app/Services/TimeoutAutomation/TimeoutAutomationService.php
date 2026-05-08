<?php

namespace App\Services\TimeoutAutomation;

use App\Domain\Commands\Dispute\OpenDisputeCommand;
use App\Domain\Commands\Order\CancelOrderCommand;
use App\Domain\Commands\Order\CompleteOrderCommand;
use App\Domain\Enums\OrderStatus;
use App\Models\EscrowTimeoutEvent;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\EscalationOperationsService;
use App\Services\Audit\AuditService;
use App\Services\Dispute\DisputeService;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class TimeoutAutomationService
{
    public function __construct(
        private readonly EscrowTimeoutSettingsService $settings = new EscrowTimeoutSettingsService(),
        private readonly TimeoutNotificationService $notifications = new TimeoutNotificationService(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function processDue(int $limit = 200): array
    {
        $counts = [
            'unpaid_cancelled' => 0,
            'unpaid_warnings_sent' => 0,
            'seller_deadline_warnings_sent' => 0,
            'seller_deadline_escalated' => 0,
            'escalation_warnings_sent' => 0,
            'admin_escalations_opened' => 0,
            'reminders_sent' => 0,
            'buyer_review_expired' => 0,
            'auto_released' => 0,
            'auto_disputed' => 0,
            'skipped_disputed' => 0,
        ];

        foreach ($this->dueUnpaidOrders($limit) as $order) {
            $counts['unpaid_cancelled'] += $this->processUnpaidExpiration($order);
        }

        foreach ($this->dueUnpaidWarnings($limit) as $order) {
            $counts['unpaid_warnings_sent'] += $this->processUnpaidWarning($order);
        }

        foreach ($this->dueSellerDeadlineWarnings($limit) as $order) {
            $counts['seller_deadline_warnings_sent'] += $this->processSellerDeadlineWarning($order);
        }

        foreach ($this->dueSellerDeadlines($limit) as $order) {
            $result = $this->processSellerDeadline($order);
            $counts['seller_deadline_escalated'] += $result['seller_deadline_escalated'];
            $counts['admin_escalations_opened'] += $result['admin_escalations_opened'];
        }

        foreach ($this->dueBuyerReviewReminder1($limit) as $order) {
            $counts['reminders_sent'] += $this->processReminder($order, 'buyer_review_reminder_1', 'Review reminder', 'Please review your delivery before the escrow timer expires.');
        }

        foreach ($this->dueBuyerReviewReminder2($limit) as $order) {
            $counts['reminders_sent'] += $this->processReminder($order, 'buyer_review_reminder_2', 'Final review reminder', 'Your delivery review window is close to expiring.');
        }

        foreach ($this->dueEscalationWarnings($limit) as $order) {
            $counts['escalation_warnings_sent'] += $this->processEscalationWarning($order);
        }

        foreach ($this->dueBuyerReviewExpiries($limit) as $order) {
            $result = $this->processBuyerReviewExpiry($order);
            foreach ($result as $key => $value) {
                $counts[$key] = ($counts[$key] ?? 0) + $value;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function timerState(Order $order): array
    {
        $order->loadMissing('escrowAccount');
        $now = now();
        $active = null;
        $nextAt = null;
        $action = null;
        $expiresAt = $order->expires_at ?? $this->derivedUnpaidExpiry($order);
        $sellerDeadlineAt = $order->seller_deadline_at ?? $this->derivedSellerDeadline($order);
        $sellerReminderAt = $order->seller_reminder_at;
        $buyerReviewExpiresAt = $order->buyer_review_expires_at ?? $this->derivedBuyerReviewExpiry($order);
        $reminder1At = $order->reminder_1_at;
        $reminder2At = $order->reminder_2_at;
        $escalationAt = $order->escalation_at ?? $buyerReviewExpiresAt;
        $escalationWarningAt = $order->escalation_warning_at;

        if ($order->status === OrderStatus::PendingPayment) {
            $active = 'unpaid_order_expiration';
            $nextAt = $this->eventExists($order->id, 'unpaid_order_expiration_warning')
                ? $expiresAt
                : ($order->unpaid_reminder_at ?? $expiresAt);
            $action = $nextAt !== null && $expiresAt !== null && $nextAt->equalTo($expiresAt)
                ? 'auto_cancel'
                : 'send_unpaid_warning';
        } elseif (in_array($order->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Processing], true)) {
            $active = 'seller_fulfillment_deadline';
            $nextAt = $this->eventExists($order->id, 'seller_fulfillment_deadline_warning')
                ? $sellerDeadlineAt
                : ($sellerReminderAt ?? $sellerDeadlineAt);
            $action = $nextAt !== null && $sellerDeadlineAt !== null && $nextAt->equalTo($sellerDeadlineAt)
                ? 'escalate_seller_deadline'
                : 'send_seller_deadline_warning';
        } elseif ($order->status === OrderStatus::BuyerReview) {
            $active = 'buyer_review';
            $nextAt = $reminder1At ?? $buyerReviewExpiresAt;
            if ($this->eventExists($order->id, 'buyer_review_reminder_1')) {
                $nextAt = $reminder2At ?? $buyerReviewExpiresAt;
            }
            if ($this->eventExists($order->id, 'buyer_review_reminder_2')) {
                $nextAt = $this->eventExists($order->id, 'buyer_review_escalation_warning')
                    ? $buyerReviewExpiresAt
                    : ($escalationWarningAt ?? $buyerReviewExpiresAt);
                $action = $nextAt !== null && $buyerReviewExpiresAt !== null && $nextAt->equalTo($buyerReviewExpiresAt)
                    ? 'buyer_review_expiry'
                    : 'send_escalation_warning';
            } else {
                $action = 'send_reminder';
            }
        }

        return [
            'active_timer' => $active,
            'next_event_at' => $nextAt?->toIso8601String(),
            'seconds_remaining' => $nextAt !== null ? max(0, $now->diffInSeconds($nextAt, false)) : null,
            'expiry_action' => $action,
            'expires_at' => $expiresAt?->toIso8601String(),
            'unpaid_reminder_at' => $order->unpaid_reminder_at?->toIso8601String(),
            'seller_deadline_at' => $sellerDeadlineAt?->toIso8601String(),
            'seller_reminder_at' => $sellerReminderAt?->toIso8601String(),
            'buyer_review_expires_at' => $buyerReviewExpiresAt?->toIso8601String(),
            'reminder_1_at' => $reminder1At?->toIso8601String(),
            'reminder_2_at' => $reminder2At?->toIso8601String(),
            'escalation_at' => $escalationAt?->toIso8601String(),
            'escalation_warning_at' => $escalationWarningAt?->toIso8601String(),
            'auto_release_at' => $order->auto_release_at?->toIso8601String(),
            'events' => EscrowTimeoutEvent::query()
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get()
                ->map(static fn (EscrowTimeoutEvent $event): array => [
                    'event_type' => $event->event_type,
                    'status' => $event->status,
                    'action_taken' => $event->action_taken,
                    'processed_at' => $event->processed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function derivedUnpaidExpiry(Order $order): ?\Illuminate\Support\Carbon
    {
        $base = $order->created_at ?? $order->updated_at;
        if ($base === null) {
            return null;
        }
        $policy = $this->policy($order);

        return $base->copy()->addMinutes((int) ($policy['unpaid_order_expiration_minutes'] ?? 30));
    }

    private function derivedSellerDeadline(Order $order): ?\Illuminate\Support\Carbon
    {
        $base = $order->placed_at ?? $order->updated_at ?? $order->created_at;
        if ($base === null) {
            return null;
        }
        $policy = $this->policy($order);

        return $base->copy()->addHours((int) ($policy['seller_fulfillment_deadline_hours'] ?? 24));
    }

    private function derivedBuyerReviewExpiry(Order $order): ?\Illuminate\Support\Carbon
    {
        $base = $order->buyer_review_started_at ?? $order->delivery_submitted_at ?? $order->updated_at;
        if ($base === null) {
            return null;
        }
        $policy = $this->policy($order);

        return $base->copy()->addHours((int) ($policy['buyer_review_deadline_hours'] ?? 72));
    }

    private function processUnpaidExpiration(Order $order): int
    {
        return DB::transaction(function () use ($order): int {
            $locked = $this->lockOrder($order->id);
            if ($locked === null || $locked->status !== OrderStatus::PendingPayment) {
                return 0;
            }
            $policy = $this->policy($locked);
            if (! (bool) ($policy['auto_cancel_unpaid_orders'] ?? true)) {
                $this->recordEvent($locked, 'unpaid_order_expired', 'skipped_auto_cancel_disabled', $locked->expires_at);
                return 0;
            }
            if ($this->recordEvent($locked, 'unpaid_order_cancelled', 'auto_cancelled', $locked->expires_at) === false) {
                return 0;
            }

            (new OrderService())->cancelOrder(new CancelOrderCommand(
                orderId: $locked->id,
                actorUserId: (int) $locked->buyer_user_id,
                reason: 'Unpaid order expired automatically.',
                correlationId: 'timeout:unpaid:'.$locked->id,
            ));

            $this->notifyBuyer($locked, 'escrow.timeout.unpaid_cancelled', 'Order cancelled', 'Your unpaid order expired and was cancelled.');
            $this->auditTimeout($locked, 'timeout.unpaid_cancelled', 'pending_payment', 'cancelled', 'unpaid_order_expiration');

            return 1;
        });
    }

    private function processUnpaidWarning(Order $order): int
    {
        return DB::transaction(function () use ($order): int {
            $locked = $this->lockOrder($order->id);
            if ($locked === null || $locked->status !== OrderStatus::PendingPayment) {
                return 0;
            }
            if ($this->recordEvent($locked, 'unpaid_order_expiration_warning', 'warning_sent', $locked->unpaid_reminder_at) === false) {
                return 0;
            }

            $this->notifyBuyer($locked, 'escrow.timeout.unpaid_warning', 'Payment reminder', 'Your unpaid order will expire soon unless payment is completed.');
            $this->auditTimeout($locked, 'timeout.unpaid_warning', $locked->status->value, $locked->status->value, 'unpaid_order_expiration_warning');

            return 1;
        });
    }

    /**
     * @return array{seller_deadline_escalated: int, admin_escalations_opened: int}
     */
    private function processSellerDeadline(Order $order): array
    {
        return DB::transaction(function () use ($order): array {
            $counts = ['seller_deadline_escalated' => 0, 'admin_escalations_opened' => 0];
            $locked = $this->lockOrder($order->id);
            if ($locked === null || $this->hasDispute($locked) || ! in_array($locked->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Processing], true)) {
                return $counts;
            }
            if ($this->recordEvent($locked, 'seller_fulfillment_deadline_missed', 'escalated', $locked->seller_deadline_at) === false) {
                return $counts;
            }

            $this->notifySeller($locked, 'escrow.timeout.seller_deadline_missed', 'Fulfillment deadline missed', 'An order has missed its fulfillment deadline and was escalated.');
            $this->notifyBuyer($locked, 'escrow.timeout.seller_deadline_missed_buyer', 'Seller deadline missed', 'The seller missed the fulfillment deadline. The order has been escalated for review.');
            $this->auditTimeout($locked, 'timeout.seller_deadline_escalated', $locked->status->value, $locked->status->value, 'seller_fulfillment_deadline');
            $counts['seller_deadline_escalated']++;

            if ($this->openAdminEscalation($locked, 'seller_fulfillment_deadline_missed', $locked->seller_deadline_at)) {
                $counts['admin_escalations_opened']++;
            }

            return $counts;
        });
    }

    private function processSellerDeadlineWarning(Order $order): int
    {
        return DB::transaction(function () use ($order): int {
            $locked = $this->lockOrder($order->id);
            if ($locked === null || $this->hasDispute($locked) || ! in_array($locked->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Processing], true)) {
                return 0;
            }
            if ($this->recordEvent($locked, 'seller_fulfillment_deadline_warning', 'warning_sent', $locked->seller_reminder_at) === false) {
                return 0;
            }

            $this->notifySeller($locked, 'escrow.timeout.seller_deadline_warning', 'Fulfillment deadline reminder', 'This order is approaching its fulfillment deadline.');
            $this->notifyBuyer($locked, 'escrow.timeout.seller_deadline_warning_buyer', 'Seller deadline approaching', 'The seller fulfillment deadline for your order is approaching.');
            $this->auditTimeout($locked, 'timeout.seller_deadline_warning', $locked->status->value, $locked->status->value, 'seller_fulfillment_deadline_warning');

            return 1;
        });
    }

    private function processReminder(Order $order, string $eventType, string $title, string $body): int
    {
        return DB::transaction(function () use ($order, $eventType, $title, $body): int {
            $locked = $this->lockOrder($order->id);
            if ($locked === null || $this->hasDispute($locked) || $locked->status !== OrderStatus::BuyerReview) {
                return 0;
            }
            $scheduled = $eventType === 'buyer_review_reminder_1' ? $locked->reminder_1_at : $locked->reminder_2_at;
            if ($this->recordEvent($locked, $eventType, 'reminder_sent', $scheduled) === false) {
                return 0;
            }

            $this->notifyBuyer($locked, 'escrow.timeout.'.$eventType, $title, $body);
            $this->auditTimeout($locked, 'timeout.'.$eventType, $locked->status->value, $locked->status->value, $eventType);

            return 1;
        });
    }

    private function processEscalationWarning(Order $order): int
    {
        return DB::transaction(function () use ($order): int {
            $locked = $this->lockOrder($order->id);
            if ($locked === null || $this->hasDispute($locked) || $locked->status !== OrderStatus::BuyerReview) {
                return 0;
            }
            if ($this->recordEvent($locked, 'buyer_review_escalation_warning', 'warning_sent', $locked->escalation_warning_at) === false) {
                return 0;
            }

            $this->notifyBuyer($locked, 'escrow.timeout.escalation_warning', 'Review deadline approaching', 'Your buyer review window is close to expiring.');
            $this->notifySeller($locked, 'escrow.timeout.escalation_warning_seller', 'Buyer review deadline approaching', 'The buyer review window is close to expiring.');
            $this->auditTimeout($locked, 'timeout.escalation_warning', $locked->status->value, $locked->status->value, 'buyer_review_escalation_warning');

            return 1;
        });
    }

    /**
     * @return array<string, int>
     */
    private function processBuyerReviewExpiry(Order $order): array
    {
        return DB::transaction(function () use ($order): array {
            $counts = ['buyer_review_expired' => 0, 'auto_released' => 0, 'auto_disputed' => 0, 'skipped_disputed' => 0];
            $locked = $this->lockOrder($order->id);
            if ($locked === null || $locked->status !== OrderStatus::BuyerReview) {
                return $counts;
            }
            if ($this->hasDispute($locked)) {
                $this->recordEvent($locked, 'buyer_review_expired_dispute_interrupt', 'skipped_disputed', $locked->buyer_review_expires_at);
                $counts['skipped_disputed']++;
                return $counts;
            }
            if ($this->recordEvent($locked, 'buyer_review_expired', 'processed', $locked->buyer_review_expires_at) === false) {
                return $counts;
            }

            $counts['buyer_review_expired']++;
            $policy = $this->policy($locked);
            if ((bool) ($policy['auto_create_dispute_on_timeout'] ?? false)) {
                (new DisputeService())->openDispute(new OpenDisputeCommand(
                    orderId: $locked->id,
                    orderItemId: null,
                    openedByUserId: (int) $locked->buyer_user_id,
                    reasonCode: 'buyer_review_timeout',
                    correlationId: 'timeout:buyer_review:'.$locked->id,
                    idempotencyKey: 'timeout:buyer_review:dispute:'.$locked->id,
                ));
                $this->recordEvent($locked, 'buyer_review_timeout_dispute_created', 'auto_dispute_created', $locked->buyer_review_expires_at);
                $this->notifyBuyer($locked, 'escrow.timeout.dispute_created', 'Review timed out', 'A dispute was opened because the buyer review window expired.');
                $this->notifySeller($locked, 'escrow.timeout.dispute_created_seller', 'Review timed out', 'A dispute was opened after the buyer review window expired.');
                $this->openAdminEscalation($locked, 'buyer_review_timeout_dispute_created', $locked->buyer_review_expires_at);
                $counts['auto_disputed']++;
                return $counts;
            }

            if ((bool) ($policy['auto_release_after_buyer_timeout'] ?? false)) {
                (new OrderService())->completeOrder(new CompleteOrderCommand(
                    orderId: $locked->id,
                    actorUserId: (int) $locked->buyer_user_id,
                    correlationId: 'timeout:auto_release:'.$locked->id,
                ));
                $this->recordEvent($locked, 'buyer_review_timeout_auto_release', 'auto_released', $locked->auto_release_at ?? $locked->buyer_review_expires_at);
                $this->notifyBuyer($locked, 'escrow.timeout.auto_release', 'Escrow released', 'The review window expired and escrow was released by platform rules.');
                $this->notifySeller($locked, 'escrow.timeout.auto_release_seller', 'Escrow released', 'Escrow was released after the buyer review timeout.');
                $counts['auto_released']++;
                return $counts;
            }

            if ((bool) ($policy['auto_escalation_after_review_expiry'] ?? true)) {
                $this->recordEvent($locked, 'buyer_review_timeout_escalated', 'escalated', $locked->escalation_at);
                $this->notifyBuyer($locked, 'escrow.timeout.escalated', 'Review expired', 'The order was escalated for review after the buyer review window expired.');
                $this->notifySeller($locked, 'escrow.timeout.escalated_seller', 'Review expired', 'The order was escalated after buyer review timeout.');
                $this->openAdminEscalation($locked, 'buyer_review_timeout_escalated', $locked->escalation_at);
            }

            return $counts;
        });
    }

    private function lockOrder(int $orderId): ?Order
    {
        return Order::query()->whereKey($orderId)->with('escrowAccount')->lockForUpdate()->first();
    }

    private function recordEvent(Order $order, string $eventType, string $action, mixed $scheduledFor): bool
    {
        $exists = EscrowTimeoutEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', $eventType)
            ->lockForUpdate()
            ->exists();
        if ($exists) {
            return false;
        }

        EscrowTimeoutEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'escrow_account_id' => $order->escrowAccount?->id,
            'event_type' => $eventType,
            'status' => 'processed',
            'action_taken' => $action,
            'metadata_json' => ['order_status' => $order->status->value],
            'scheduled_for' => $scheduledFor,
            'processed_at' => now(),
        ]);

        return true;
    }

    private function eventExists(int $orderId, string $eventType): bool
    {
        return EscrowTimeoutEvent::query()->where('order_id', $orderId)->where('event_type', $eventType)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function policy(Order $order): array
    {
        if (is_array($order->timeout_policy_snapshot_json)) {
            return $order->timeout_policy_snapshot_json;
        }

        return $this->settings->current()->toArray();
    }

    private function hasDispute(Order $order): bool
    {
        return $order->status === OrderStatus::Disputed || $order->disputeCases()->where('status', '!=', 'resolved')->exists();
    }

    private function notifyBuyer(Order $order, string $template, string $title, string $body): void
    {
        $this->notifications->notify((int) $order->buyer_user_id, $template, $title, $body, [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
        ]);
    }

    private function notifySeller(Order $order, string $template, string $title, string $body): void
    {
        if ((int) ($order->seller_user_id ?? 0) <= 0) {
            return;
        }
        $this->notifications->notify((int) $order->seller_user_id, $template, $title, $body, [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
        ]);
    }

    private function auditTimeout(Order $order, string $action, string $beforeState, string $afterState, string $timerType): void
    {
        $this->audit->record(
            actorId: $this->systemActorId(),
            actorRole: 'system',
            action: $action,
            targetType: 'order',
            targetId: (int) $order->id,
            before: ['state' => $beforeState, 'timer_type' => $timerType],
            after: ['state' => $afterState],
            reasonCode: $timerType,
            correlationId: 'timeout:'.substr(sha1($timerType.':'.$order->id), 0, 24),
        );
    }

    private function openAdminEscalation(Order $order, string $reasonCode, mixed $breachedAt): bool
    {
        $policy = $this->policy($order);
        if (! (bool) ($policy['dispute_review_queue_enabled'] ?? true)) {
            return false;
        }
        if (! Schema::hasTable('admin_escalation_incidents')) {
            return false;
        }

        try {
            app(EscalationOperationsService::class)->openFromBreach(
                queueCode: 'escrow_timeouts',
                targetType: 'order',
                targetId: (int) $order->id,
                reasonCode: $reasonCode,
                breachedAt: $breachedAt instanceof \DateTimeInterface ? $breachedAt : now(),
                meta: [
                    'source' => 'escrow_timeout_automation',
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'buyer_user_id' => $order->buyer_user_id,
                    'seller_user_id' => $order->seller_user_id,
                ],
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function systemActorId(): ?int
    {
        return User::query()->where('email', 'system@sellova.local')->value('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    private function dueUnpaidOrders(int $limit)
    {
        return Order::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->get();
    }

    private function dueUnpaidWarnings(int $limit)
    {
        return Order::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('unpaid_reminder_at')
            ->where('unpaid_reminder_at', '<=', now())
            ->whereDoesntHave('escrowTimeoutEvents', static fn ($q) => $q->where('event_type', 'unpaid_order_expiration_warning'))
            ->limit($limit)
            ->get();
    }

    private function dueSellerDeadlineWarnings(int $limit)
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::EscrowFunded->value, OrderStatus::PaidInEscrow->value, OrderStatus::Processing->value])
            ->whereNotNull('seller_reminder_at')
            ->where('seller_reminder_at', '<=', now())
            ->whereDoesntHave('disputeCases', static fn ($q) => $q->where('status', '!=', 'resolved'))
            ->whereDoesntHave('escrowTimeoutEvents', static fn ($q) => $q->where('event_type', 'seller_fulfillment_deadline_warning'))
            ->limit($limit)
            ->get();
    }

    private function dueSellerDeadlines(int $limit)
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::EscrowFunded->value, OrderStatus::PaidInEscrow->value, OrderStatus::Processing->value])
            ->whereNotNull('seller_deadline_at')
            ->where('seller_deadline_at', '<=', now())
            ->whereDoesntHave('disputeCases', static fn ($q) => $q->where('status', '!=', 'resolved'))
            ->limit($limit)
            ->get();
    }

    private function dueBuyerReviewReminder1(int $limit)
    {
        return Order::query()
            ->where('status', OrderStatus::BuyerReview->value)
            ->whereNotNull('reminder_1_at')
            ->where('reminder_1_at', '<=', now())
            ->whereDoesntHave('disputeCases', static fn ($q) => $q->where('status', '!=', 'resolved'))
            ->whereDoesntHave('escrowTimeoutEvents', static fn ($q) => $q->where('event_type', 'buyer_review_reminder_1'))
            ->limit($limit)
            ->get();
    }

    private function dueBuyerReviewReminder2(int $limit)
    {
        return Order::query()
            ->where('status', OrderStatus::BuyerReview->value)
            ->whereNotNull('reminder_2_at')
            ->where('reminder_2_at', '<=', now())
            ->whereDoesntHave('disputeCases', static fn ($q) => $q->where('status', '!=', 'resolved'))
            ->whereDoesntHave('escrowTimeoutEvents', static fn ($q) => $q->where('event_type', 'buyer_review_reminder_2'))
            ->limit($limit)
            ->get();
    }

    private function dueEscalationWarnings(int $limit)
    {
        return Order::query()
            ->where('status', OrderStatus::BuyerReview->value)
            ->whereNotNull('escalation_warning_at')
            ->where('escalation_warning_at', '<=', now())
            ->whereDoesntHave('disputeCases', static fn ($q) => $q->where('status', '!=', 'resolved'))
            ->whereDoesntHave('escrowTimeoutEvents', static fn ($q) => $q->where('event_type', 'buyer_review_escalation_warning'))
            ->limit($limit)
            ->get();
    }

    private function dueBuyerReviewExpiries(int $limit)
    {
        return Order::query()
            ->where('status', OrderStatus::BuyerReview->value)
            ->whereNotNull('buyer_review_expires_at')
            ->where('buyer_review_expires_at', '<=', now())
            ->whereDoesntHave('escrowTimeoutEvents', static fn ($q) => $q->where('event_type', 'buyer_review_expired'))
            ->limit($limit)
            ->get();
    }
}

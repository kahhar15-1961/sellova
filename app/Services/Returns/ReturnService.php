<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Domain\Exceptions\AuthValidationFailedException;
use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Models\Order;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ReturnService
{
    public function createBuyerReturnRequest(User $actor, array $payload): array
    {
        $this->ensureTables();
        $orderId = (int) ($payload['order_id'] ?? 0);
        $reasonCode = trim((string) ($payload['reason_code'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $evidence = $payload['evidence'] ?? [];

        if ($orderId <= 0 || $reasonCode === '') {
            throw new AuthValidationFailedException('validation_failed', ['order_id' => $orderId, 'reason_code' => $reasonCode]);
        }

        /** @var Order|null $order */
        $order = Order::query()->whereKey($orderId)->first();
        if ($order === null) {
            throw new AuthValidationFailedException('not_found', ['order_id' => $orderId]);
        }
        if ((int) $order->buyer_user_id !== (int) $actor->id) {
            throw new DomainAuthorizationDeniedException('returns.create', (int) $actor->id);
        }
        $eligibility = $this->computeEligibility($order, (int) $actor->id);
        if (! $eligibility['eligible']) {
            throw new AuthValidationFailedException('validation_failed', ['eligibility' => $eligibility]);
        }
        $sellerUserId = (int) (Order::query()
            ->leftJoin('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('seller_profiles', 'seller_profiles.id', '=', 'order_items.seller_profile_id')
            ->where('orders.id', $orderId)
            ->value('seller_profiles.user_id') ?? 0);

        $return = ReturnRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'rma_code' => $this->generateRmaCode(),
            'order_id' => $orderId,
            'buyer_user_id' => (int) $actor->id,
            'seller_user_id' => $sellerUserId > 0 ? $sellerUserId : null,
            'reason_code' => $reasonCode,
            'notes' => $notes !== '' ? $notes : null,
            'evidence_json' => is_array($evidence) ? array_values($evidence) : [],
            'status' => 'requested',
            'resolution_code' => null,
            'reverse_logistics_status' => 'pending_buyer_shipment',
            'refund_status' => 'not_started',
            'requested_at' => now(),
            'sla_due_at' => now()->addHours(72),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordEvent($return, 'requested', (int) $actor->id, ['reason_code' => $reasonCode]);
        $this->notifyUsers($return, 'return_requested');

        return $this->toArray($return->fresh(), true);
    }

    public function listBuyerReturns(User $actor): array
    {
        $this->ensureTables();
        $items = ReturnRequest::query()
            ->where('buyer_user_id', $actor->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReturnRequest $item): array => $this->toArray($item, false))
            ->values()
            ->all();

        return ['items' => $items];
    }

    public function getReturnDetail(User $actor, int $returnRequestId): array
    {
        $this->ensureTables();
        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if (! $this->canAccess($actor, $return)) {
            throw new DomainAuthorizationDeniedException('returns.view', (int) $actor->id);
        }

        return $this->toArray($return, true);
    }

    public function listSellerReturns(User $actor): array
    {
        $this->ensureTables();
        $this->assertSeller($actor);

        $items = ReturnRequest::query()
            ->where('seller_user_id', $actor->id)
            ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReturnRequest $item): array => $this->toArray($item, false))
            ->values()
            ->all();

        return ['items' => $items];
    }

    public function decideSellerReturn(User $actor, int $returnRequestId, array $payload): array
    {
        $this->ensureTables();
        $this->assertSeller($actor);
        $decision = strtolower(trim((string) ($payload['decision'] ?? '')));
        $decisionNote = trim((string) ($payload['decision_note'] ?? ''));
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new AuthValidationFailedException('validation_failed', ['decision' => $decision]);
        }
        if ($decision === 'reject' && $decisionNote === '') {
            throw new AuthValidationFailedException('validation_failed', ['decision_note' => 'required_for_reject']);
        }

        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if ((int) $return->seller_user_id !== (int) $actor->id) {
            throw new DomainAuthorizationDeniedException('returns.decide', (int) $actor->id);
        }
        if ((string) $return->status !== 'requested') {
            throw new AuthValidationFailedException('validation_failed', ['status' => $return->status]);
        }

        $return->status = $decision === 'approve' ? 'approved' : 'rejected';
        $return->resolution_code = $decision === 'approve' ? 'seller_approved' : 'seller_rejected';
        $return->decision_note = $decisionNote !== '' ? $decisionNote : null;
        $return->decided_by_user_id = (int) $actor->id;
        $return->decided_at = now();
        $return->updated_at = now();
        $return->save();

        $this->recordEvent($return, $return->status, (int) $actor->id, ['decision_note' => $return->decision_note]);

        return $this->toArray($return->fresh(), true);
    }

    public function eligibilityForOrder(User $actor, int $orderId): array
    {
        $this->ensureTables();
        /** @var Order|null $order */
        $order = Order::query()->whereKey($orderId)->first();
        if ($order === null) {
            throw new AuthValidationFailedException('not_found', ['order_id' => $orderId]);
        }
        if ((int) $order->buyer_user_id !== (int) $actor->id && ! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.eligibility', (int) $actor->id);
        }

        return $this->computeEligibility($order, (int) $order->buyer_user_id);
    }

    public function listAdminQueue(User $actor): array
    {
        $this->ensureTables();
        if (! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.admin.queue', (int) $actor->id);
        }

        $items = ReturnRequest::query()
            ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (ReturnRequest $item): array => $this->toArray($item, false))
            ->values()
            ->all();

        return ['items' => $items];
    }

    public function escalate(User $actor, int $returnRequestId, ?string $note): array
    {
        $this->ensureTables();
        if (! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.admin.escalate', (int) $actor->id);
        }
        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if (! in_array((string) $return->status, ['requested', 'approved'], true)) {
            throw new AuthValidationFailedException('validation_failed', ['status' => $return->status]);
        }

        $return->status = 'escalated';
        $return->escalated_at = now();
        $return->decision_note = $note !== null && trim($note) !== '' ? trim($note) : $return->decision_note;
        $return->updated_at = now();
        $return->save();

        $this->recordEvent($return, 'escalated', (int) $actor->id, ['note' => $note]);
        $this->notifyUsers($return, 'return_escalated');

        return $this->toArray($return->fresh(), true);
    }

    public function adminAnalytics(User $actor): array
    {
        $this->ensureTables();
        if (! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.admin.analytics', (int) $actor->id);
        }

        $statusCounts = ReturnRequest::query()
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();
        $reasonCounts = ReturnRequest::query()
            ->selectRaw('reason_code, COUNT(*) AS aggregate')
            ->groupBy('reason_code')
            ->pluck('aggregate', 'reason_code')
            ->toArray();
        $overdueCount = ReturnRequest::query()
            ->whereIn('status', ['requested', 'approved', 'escalated'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();

        return [
            'status_counts' => $statusCounts,
            'reason_counts' => $reasonCounts,
            'overdue_count' => $overdueCount,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function markBuyerShipped(User $actor, int $returnRequestId, ?string $trackingUrl, ?string $carrier): array
    {
        $this->ensureTables();
        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if ((int) $return->buyer_user_id !== (int) $actor->id) {
            throw new DomainAuthorizationDeniedException('returns.buyer.shipped', (int) $actor->id);
        }
        if ((string) $return->status !== 'approved') {
            throw new AuthValidationFailedException('validation_failed', ['status' => $return->status]);
        }

        $return->reverse_logistics_status = 'in_transit_to_seller';
        $return->return_tracking_url = $trackingUrl !== null && trim($trackingUrl) !== '' ? trim($trackingUrl) : $return->return_tracking_url;
        $return->return_carrier = $carrier !== null && trim($carrier) !== '' ? trim($carrier) : $return->return_carrier;
        $return->updated_at = now();
        $return->save();
        $this->recordEvent($return, 'buyer_shipped_back', (int) $actor->id, [
            'tracking_url' => $return->return_tracking_url,
            'carrier' => $return->return_carrier,
        ]);

        return $this->toArray($return->fresh(), true);
    }

    public function markSellerReceived(User $actor, int $returnRequestId): array
    {
        $this->ensureTables();
        $this->assertSeller($actor);
        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if ((int) $return->seller_user_id !== (int) $actor->id && ! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.seller.received', (int) $actor->id);
        }
        if (! in_array((string) $return->reverse_logistics_status, ['in_transit_to_seller', 'pending_buyer_shipment'], true)) {
            throw new AuthValidationFailedException('validation_failed', ['reverse_logistics_status' => $return->reverse_logistics_status]);
        }

        $return->reverse_logistics_status = 'received_by_seller';
        $return->updated_at = now();
        $return->save();
        $this->recordEvent($return, 'seller_received_return', (int) $actor->id);

        return $this->toArray($return->fresh(), true);
    }

    public function submitRefund(User $actor, int $returnRequestId, ?string $amount): array
    {
        $this->ensureTables();
        if (! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.admin.refund.submit', (int) $actor->id);
        }
        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if (! in_array((string) $return->status, ['approved', 'escalated'], true)) {
            throw new AuthValidationFailedException('validation_failed', ['status' => $return->status]);
        }

        $refundAmount = $amount !== null ? trim($amount) : null;
        $return->refund_status = 'submitted';
        $return->refund_amount = $refundAmount !== '' ? $refundAmount : $return->refund_amount;
        $return->refund_submitted_at = now();
        $return->updated_at = now();
        $return->save();
        $this->recordEvent($return, 'refund_submitted', (int) $actor->id, ['refund_amount' => $return->refund_amount]);

        return $this->toArray($return->fresh(), true);
    }

    public function confirmRefund(User $actor, int $returnRequestId): array
    {
        $this->ensureTables();
        if (! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.admin.refund.confirm', (int) $actor->id);
        }
        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if ((string) $return->refund_status !== 'submitted') {
            throw new AuthValidationFailedException('validation_failed', ['refund_status' => $return->refund_status]);
        }

        $return->refund_status = 'confirmed';
        $return->status = 'refunded';
        $return->resolution_code = 'refund_completed';
        $return->refunded_at = now();
        $return->updated_at = now();
        $return->save();
        $this->recordEvent($return, 'refund_confirmed', (int) $actor->id);
        $this->notifyUsers($return, 'refund_confirmed');

        return $this->toArray($return->fresh(), true);
    }

    public function failRefund(User $actor, int $returnRequestId, ?string $reason): array
    {
        $this->ensureTables();
        if (! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.admin.refund.fail', (int) $actor->id);
        }
        /** @var ReturnRequest|null $return */
        $return = ReturnRequest::query()->whereKey($returnRequestId)->first();
        if ($return === null) {
            throw new AuthValidationFailedException('not_found', ['return_request_id' => $returnRequestId]);
        }
        if ((string) $return->refund_status !== 'submitted') {
            throw new AuthValidationFailedException('validation_failed', ['refund_status' => $return->refund_status]);
        }

        $return->refund_status = 'failed';
        $return->updated_at = now();
        $return->save();
        $this->recordEvent($return, 'refund_failed', (int) $actor->id, ['reason' => $reason]);
        $this->notifyUsers($return, 'refund_failed');

        return $this->toArray($return->fresh(), true);
    }

    public function autoEscalateOverdue(User $actor): array
    {
        $this->ensureTables();
        if (! $actor->isPlatformStaff()) {
            throw new DomainAuthorizationDeniedException('returns.admin.auto_escalate', (int) $actor->id);
        }

        $candidates = ReturnRequest::query()
            ->whereIn('status', ['requested', 'approved'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->limit(100)
            ->get();

        $updated = 0;
        foreach ($candidates as $return) {
            $return->status = 'escalated';
            $return->escalated_at = now();
            $return->updated_at = now();
            $return->save();
            $this->recordEvent($return, 'sla_auto_escalated', (int) $actor->id);
            $this->notifyUsers($return, 'sla_auto_escalated');
            $updated++;
        }

        return ['updated' => $updated];
    }

    private function assertSeller(User $actor): void
    {
        $hasSellerRole = UserRole::query()
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $actor->id)
            ->where('roles.code', 'seller')
            ->exists();

        if (! $hasSellerRole && ! $actor->isPlatformStaff()) {
            $hasSellerProfile = SellerProfile::query()->where('user_id', $actor->id)->exists();
            if (! $hasSellerProfile) {
                throw new DomainAuthorizationDeniedException('returns.seller', (int) $actor->id);
            }
        }
    }

    private function canAccess(User $actor, ReturnRequest $return): bool
    {
        return (int) $return->buyer_user_id === (int) $actor->id
            || (int) $return->seller_user_id === (int) $actor->id
            || $actor->isPlatformStaff();
    }

    private function recordEvent(ReturnRequest $return, string $eventCode, int $actorUserId, array $meta = []): void
    {
        ReturnRequestEvent::query()->create([
            'return_request_id' => (int) $return->id,
            'event_code' => $eventCode,
            'actor_user_id' => $actorUserId,
            'meta_json' => $meta,
            'created_at' => now(),
        ]);
    }

    private function toArray(ReturnRequest $return, bool $withTimeline): array
    {
        $data = [
            'id' => (int) $return->id,
            'uuid' => (string) ($return->uuid ?? ''),
            'rma_code' => $return->rma_code,
            'order_id' => (int) $return->order_id,
            'buyer_user_id' => (int) $return->buyer_user_id,
            'seller_user_id' => $return->seller_user_id !== null ? (int) $return->seller_user_id : null,
            'reason_code' => (string) $return->reason_code,
            'notes' => $return->notes,
            'evidence' => is_array($return->evidence_json) ? $return->evidence_json : [],
            'status' => (string) $return->status,
            'resolution_code' => $return->resolution_code,
            'reverse_logistics_status' => $return->reverse_logistics_status,
            'return_tracking_url' => $return->return_tracking_url,
            'return_carrier' => $return->return_carrier,
            'refund_status' => $return->refund_status,
            'refund_amount' => $return->refund_amount !== null ? (string) $return->refund_amount : null,
            'refund_submitted_at' => $return->refund_submitted_at?->toIso8601String(),
            'refunded_at' => $return->refunded_at?->toIso8601String(),
            'decision_note' => $return->decision_note,
            'requested_at' => $return->requested_at?->toIso8601String(),
            'sla_due_at' => $return->sla_due_at?->toIso8601String(),
            'escalated_at' => $return->escalated_at?->toIso8601String(),
            'decided_at' => $return->decided_at?->toIso8601String(),
            'updated_at' => $return->updated_at?->toIso8601String(),
            'sla_status' => $this->slaStatus($return),
        ];

        if (! $withTimeline) {
            return $data;
        }

        /** @var Collection<int, ReturnRequestEvent> $events */
        $events = ReturnRequestEvent::query()
            ->where('return_request_id', $return->id)
            ->orderBy('id')
            ->get();

        $data['timeline'] = $events->map(static function (ReturnRequestEvent $event): array {
            return [
                'id' => (int) $event->id,
                'event_code' => (string) $event->event_code,
                'actor_user_id' => $event->actor_user_id !== null ? (int) $event->actor_user_id : null,
                'meta' => is_array($event->meta_json) ? $event->meta_json : [],
                'created_at' => $event->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return $data;
    }

    private function ensureTables(): void
    {
        if (! Schema::hasTable('return_requests')) {
            Schema::create('return_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->string('rma_code', 64)->nullable();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('order_item_id')->nullable();
                $table->unsignedBigInteger('buyer_user_id');
                $table->unsignedBigInteger('seller_user_id')->nullable();
                $table->string('reason_code', 64);
                $table->text('notes')->nullable();
                $table->json('evidence_json')->nullable();
                $table->string('status', 32)->default('requested');
                $table->string('resolution_code', 64)->nullable();
                $table->string('reverse_logistics_status', 32)->nullable();
                $table->string('return_tracking_url', 500)->nullable();
                $table->string('return_carrier', 100)->nullable();
                $table->string('refund_status', 32)->nullable();
                $table->decimal('refund_amount', 12, 4)->nullable();
                $table->timestamp('refund_submitted_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->string('decision_note', 255)->nullable();
                $table->unsignedBigInteger('decided_by_user_id')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('sla_due_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('return_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('return_requests', 'rma_code')) {
                    $table->string('rma_code', 64)->nullable()->after('uuid');
                }
                if (! Schema::hasColumn('return_requests', 'resolution_code')) {
                    $table->string('resolution_code', 64)->nullable()->after('status');
                }
                if (! Schema::hasColumn('return_requests', 'sla_due_at')) {
                    $table->timestamp('sla_due_at')->nullable()->after('requested_at');
                }
                if (! Schema::hasColumn('return_requests', 'escalated_at')) {
                    $table->timestamp('escalated_at')->nullable()->after('sla_due_at');
                }
                if (! Schema::hasColumn('return_requests', 'reverse_logistics_status')) {
                    $table->string('reverse_logistics_status', 32)->nullable()->after('resolution_code');
                }
                if (! Schema::hasColumn('return_requests', 'return_tracking_url')) {
                    $table->string('return_tracking_url', 500)->nullable()->after('reverse_logistics_status');
                }
                if (! Schema::hasColumn('return_requests', 'return_carrier')) {
                    $table->string('return_carrier', 100)->nullable()->after('return_tracking_url');
                }
                if (! Schema::hasColumn('return_requests', 'refund_status')) {
                    $table->string('refund_status', 32)->nullable()->after('return_carrier');
                }
                if (! Schema::hasColumn('return_requests', 'refund_amount')) {
                    $table->decimal('refund_amount', 12, 4)->nullable()->after('refund_status');
                }
                if (! Schema::hasColumn('return_requests', 'refund_submitted_at')) {
                    $table->timestamp('refund_submitted_at')->nullable()->after('refund_amount');
                }
                if (! Schema::hasColumn('return_requests', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable()->after('refund_submitted_at');
                }
            });
        }

        if (! Schema::hasTable('return_request_events')) {
            Schema::create('return_request_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('return_request_id');
                $table->string('event_code', 64);
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    private function computeEligibility(Order $order, int $buyerUserId): array
    {
        if ((int) $order->buyer_user_id !== $buyerUserId) {
            return ['eligible' => false, 'reason' => 'not_order_owner', 'window_days' => 14];
        }
        $completedAt = $order->completed_at;
        if (! $completedAt instanceof Carbon) {
            return ['eligible' => false, 'reason' => 'order_not_completed', 'window_days' => 14];
        }
        $windowDays = 14;
        $deadline = $completedAt->copy()->addDays($windowDays)->endOfDay();
        $eligible = now()->lte($deadline);
        return [
            'eligible' => $eligible,
            'reason' => $eligible ? 'ok' : 'return_window_expired',
            'window_days' => $windowDays,
            'order_completed_at' => $completedAt->toIso8601String(),
            'request_deadline_at' => $deadline->toIso8601String(),
        ];
    }

    private function generateRmaCode(): string
    {
        return 'RMA-'.Str::upper(Str::random(8));
    }

    private function slaStatus(ReturnRequest $return): string
    {
        if ($return->status === 'rejected') {
            return 'closed_rejected';
        }
        if ($return->status === 'approved' || $return->status === 'refunded') {
            return 'closed_approved';
        }
        if ($return->status === 'escalated') {
            return 'escalated';
        }
        if ($return->sla_due_at !== null && $return->sla_due_at->isPast()) {
            return 'overdue';
        }

        return 'on_track';
    }

    private function notifyUsers(ReturnRequest $return, string $templateCode): void
    {
        foreach (array_filter([(int) $return->buyer_user_id, (int) ($return->seller_user_id ?? 0)]) as $userId) {
            $role = $userId === (int) ($return->seller_user_id ?? 0) ? Notification::ROLE_SELLER : Notification::ROLE_BUYER;

            Notification::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'user_role' => $role,
                'channel' => 'in_app',
                'template_code' => $templateCode,
                'payload_json' => [
                    'title' => 'Return request updated',
                    'body' => 'A return or refund case has been updated.',
                    'href' => $role === Notification::ROLE_SELLER ? '/seller/returns' : '/refund-requests',
                    'return_request_id' => (int) $return->id,
                    'order_id' => (int) $return->order_id,
                    'status' => (string) $return->status,
                    'rma_code' => (string) ($return->rma_code ?? ''),
                ],
                'status' => 'sent',
                'sent_at' => now(),
                'read_at' => null,
            ]);
        }
    }
}

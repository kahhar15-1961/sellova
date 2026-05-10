<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\Escrow\MarkEscrowUnderDisputeCommand;
use App\Domain\Commands\Escrow\RefundEscrowCommand;
use App\Domain\Commands\Escrow\ReleaseEscrowCommand;
use App\Domain\Commands\Escrow\SettleEscrowFromDisputeCommand;
use App\Models\EscrowAccount;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use App\Services\Escrow\EscrowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class EscrowActionController extends AdminPageController
{
    public function __construct(
        private readonly EscrowService $escrows,
    ) {}

    public function store(Request $request, EscrowAccount $escrow): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validate([
            'action' => ['required', 'string', 'in:release,refund,dispute,settle,extend_deadline'],
            'reason_code' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'gt:0'],
            'dispute_case_id' => ['nullable', 'integer', 'exists:dispute_cases,id'],
            'buyer_refund_amount' => ['nullable', 'numeric', 'gte:0'],
            'seller_release_amount' => ['nullable', 'numeric', 'gte:0'],
            'delivery_deadline_at' => ['nullable', 'date'],
            'dispute_deadline_at' => ['nullable', 'date'],
            'escrow_expires_at' => ['nullable', 'date'],
        ]);

        $before = [
            'state' => $escrow->state->value,
            'held_amount' => (string) $escrow->held_amount,
            'released_amount' => (string) $escrow->released_amount,
            'refunded_amount' => (string) $escrow->refunded_amount,
            'expires_at' => $escrow->expires_at?->toIso8601String(),
            'delivery_deadline_at' => $escrow->delivery_deadline_at?->toIso8601String(),
            'dispute_deadline_at' => $escrow->dispute_deadline_at?->toIso8601String(),
        ];

        try {
            $result = match ($data['action']) {
                'release' => $this->escrows->releaseEscrow(new ReleaseEscrowCommand(
                    escrowAccountId: $escrow->id,
                    idempotencyKey: (string) Str::ulid(),
                )),
                'refund' => $this->escrows->refundEscrow(new RefundEscrowCommand(
                    escrowAccountId: $escrow->id,
                    idempotencyKey: (string) Str::ulid(),
                    refundAmount: $data['refund_amount'] ?? null,
                )),
                'dispute' => $this->escrows->markUnderDispute(new MarkEscrowUnderDisputeCommand(
                    escrowAccountId: $escrow->id,
                    disputeCaseId: (int) $data['dispute_case_id'],
                )),
                'settle' => $this->escrows->settleEscrowFromDispute(new SettleEscrowFromDisputeCommand(
                    escrowAccountId: $escrow->id,
                    disputeCaseId: (int) $data['dispute_case_id'],
                    buyerRefundAmount: (string) ($data['buyer_refund_amount'] ?? '0.0000'),
                    sellerReleaseAmount: (string) ($data['seller_release_amount'] ?? '0.0000'),
                    idempotencyKey: (string) Str::ulid(),
                )),
                'extend_deadline' => tap([
                    'delivery_deadline_at' => $data['delivery_deadline_at'] ?? null,
                    'dispute_deadline_at' => $data['dispute_deadline_at'] ?? null,
                    'escrow_expires_at' => $data['escrow_expires_at'] ?? null,
                ], function (array $dates) use ($escrow): void {
                    $order = $escrow->order()->first();
                    if ($dates['delivery_deadline_at']) {
                        $escrow->delivery_deadline_at = $dates['delivery_deadline_at'];
                        if ($order) {
                            $order->delivery_deadline_at = $dates['delivery_deadline_at'];
                            $order->seller_deadline_at = $dates['delivery_deadline_at'];
                        }
                    }
                    if ($dates['dispute_deadline_at']) {
                        $escrow->dispute_deadline_at = $dates['dispute_deadline_at'];
                        if ($order) {
                            $order->dispute_deadline_at = $dates['dispute_deadline_at'];
                            $order->buyer_review_expires_at = $dates['dispute_deadline_at'];
                        }
                    }
                    if ($dates['escrow_expires_at']) {
                        $escrow->expires_at = $dates['escrow_expires_at'];
                        if ($order) {
                            $order->escrow_expires_at = $dates['escrow_expires_at'];
                            $order->escrow_auto_release_at = $dates['escrow_expires_at'];
                        }
                    }
                    $escrow->save();
                    $order?->save();
                }),
                default => throw new \RuntimeException('Unsupported action.'),
            };
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.escrows.show', $escrow)
                ->withErrors(['action' => $e->getMessage()]);
        }

        $escrow->refresh();
        $after = [
            'state' => $escrow->state->value,
            'held_amount' => (string) $escrow->held_amount,
            'released_amount' => (string) $escrow->released_amount,
            'refunded_amount' => (string) $escrow->refunded_amount,
            'expires_at' => $escrow->expires_at?->toIso8601String(),
            'delivery_deadline_at' => $escrow->delivery_deadline_at?->toIso8601String(),
            'dispute_deadline_at' => $escrow->dispute_deadline_at?->toIso8601String(),
            'notes' => $data['notes'] ?? null,
            'result' => $result,
        ];

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.escrow.'.$data['action'],
            targetType: 'escrow_account',
            targetId: $escrow->id,
            beforeJson: $before,
            afterJson: $after,
            reasonCode: (string) $data['reason_code'],
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()
            ->route('admin.escrows.show', $escrow)
            ->with('success', 'Escrow action recorded.');
    }
}

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
            'action' => ['required', 'string', 'in:release,refund,dispute,settle'],
            'reason_code' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'gt:0'],
            'dispute_case_id' => ['nullable', 'integer', 'exists:dispute_cases,id'],
            'buyer_refund_amount' => ['nullable', 'numeric', 'gte:0'],
            'seller_release_amount' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $before = [
            'state' => $escrow->state->value,
            'held_amount' => (string) $escrow->held_amount,
            'released_amount' => (string) $escrow->released_amount,
            'refunded_amount' => (string) $escrow->refunded_amount,
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

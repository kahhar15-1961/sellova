<?php

namespace App\Services\Dispute;

use App\Domain\Commands\Dispute\EscalateDisputeCommand;
use App\Domain\Commands\Dispute\MoveDisputeToReviewCommand;
use App\Domain\Commands\Dispute\OpenDisputeCommand;
use App\Domain\Commands\Dispute\ResolveDisputeCommand;
use App\Domain\Commands\Dispute\SubmitDisputeEvidenceCommand;
use App\Domain\Commands\Escrow\MarkEscrowUnderDisputeCommand;
use App\Domain\Commands\Escrow\SettleEscrowFromDisputeCommand;
use App\Domain\Commands\Order\ApplyOrderStatusAfterDisputeResolutionCommand;
use App\Domain\Commands\Order\MarkOrderDisputedCommand;
use App\Domain\Queries\Disputes\DisputeListQuery;
use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\DisputeResolutionOutcome;
use App\Domain\Enums\EscrowState;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\OrderStatus;
use App\Domain\Exceptions\DisputeResolutionConflictException;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidDisputeStateTransitionException;
use App\Domain\Exceptions\InvalidEscrowStateTransitionException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Models\DisputeCase;
use App\Models\DisputeDecision;
use App\Models\DisputeEvidence;
use App\Models\EscrowAccount;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Escrow\EscrowService;
use App\Services\Order\OrderService;
use App\Services\Support\FinancialCritical;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DisputeService
{
    use FinancialCritical;

    private const EVIDENCE_TYPES = ['text', 'image', 'video', 'document', 'tracking', 'chat_message', 'delivery_proof', 'screenshot', 'file'];

    private readonly EscrowService $escrowService;

    private readonly OrderService $orderService;

    public function __construct(
        ?WalletLedgerService $walletLedgerService = null,
        ?EscrowService $escrowService = null,
        ?OrderService $orderService = null,
    ) {
        $ledger = $walletLedgerService ?? new WalletLedgerService();
        $this->escrowService = $escrowService ?? new EscrowService($ledger);
        $this->orderService = $orderService ?? new OrderService($ledger, $this->escrowService);
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function listDisputeCases(DisputeListQuery $query): array
    {
        $builder = DisputeCase::query()->with(['order'])->orderByDesc('id');
        if (! $query->viewerIsPlatformStaff) {
            $uid = $query->viewerUserId;
            $builder->where(function ($w) use ($uid): void {
                $w->where('opened_by_user_id', $uid)
                    ->orWhereHas('order', function ($oq) use ($uid): void {
                        $oq->where('buyer_user_id', $uid)
                            ->orWhere('seller_user_id', $uid);
                    });
            });
        }

        $page = max(1, $query->page);
        $perPage = min(100, max(1, $query->perPage));
        $total = (int) $builder->count();
        $rows = (clone $builder)->forPage($page, $perPage)->get();
        $items = [];
        foreach ($rows as $case) {
            $items[] = [
                'id' => $case->id,
                'uuid' => $case->uuid,
                'order_id' => $case->order_id,
                'status' => $case->status->value,
                'opened_by_user_id' => $case->opened_by_user_id,
                'opened_at' => $case->opened_at?->toIso8601String(),
                'resolution_outcome' => $case->resolution_outcome?->value,
            ];
        }
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ];
    }

    public function openDispute(OpenDisputeCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            if ($command->orderItemId !== null) {
                $item = OrderItem::query()
                    ->whereKey($command->orderItemId)
                    ->where('order_id', $order->id)
                    ->first();
                if ($item === null) {
                    throw new DisputeResolutionConflictException(
                        disputeCaseId: 0,
                        reasonCode: 'order_item_not_on_order',
                    );
                }
            }

            $idem = null;
            if ($command->idempotencyKey !== null) {
                $requestHash = $this->hashPayload([
                    'order_id' => $command->orderId,
                    'order_item_id' => $command->orderItemId,
                    'opened_by_user_id' => $command->openedByUserId,
                    'reason_code' => $command->reasonCode,
                ]);
                $idem = $this->claimDisputeIdempotency('dispute_open', $command->idempotencyKey, $requestHash);
                if ($idem['replay']) {
                    $existing = $this->findReplayableOpenDispute($command);
                    if ($existing === null) {
                        throw new IdempotencyConflictException($command->idempotencyKey, 'dispute_open');
                    }

                    $escrowReplay = EscrowAccount::query()
                        ->where('order_id', $order->id)
                        ->lockForUpdate()
                        ->first();
                    if ($escrowReplay === null) {
                        throw new DisputeResolutionConflictException(0, 'escrow_not_found_for_order');
                    }

                    return [
                        'dispute_case_id' => $existing->id,
                        'order_id' => $existing->order_id,
                        'escrow_account_id' => $escrowReplay->id,
                        'status' => $existing->status->value,
                        'idempotent_replay' => true,
                    ];
                }
            }

            if ($this->hasActiveDisputeOnOrder($order->id)) {
                throw new DisputeResolutionConflictException(0, 'active_dispute_exists_for_order');
            }

            if (! in_array($order->status, [
                OrderStatus::PaidInEscrow,
                OrderStatus::EscrowFunded,
                OrderStatus::Processing,
                OrderStatus::DeliverySubmitted,
                OrderStatus::BuyerReview,
            ], true)) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::Disputed->value,
                );
            }

            $escrow = EscrowAccount::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();
            if ($escrow === null) {
                throw new DisputeResolutionConflictException(0, 'escrow_not_found_for_order');
            }
            if ($escrow->state !== EscrowState::Held) {
                throw new InvalidEscrowStateTransitionException(
                    escrowAccountId: $escrow->id,
                    fromState: $escrow->state->value,
                    toState: EscrowState::UnderDispute->value,
                );
            }

            $case = DisputeCase::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'order_item_id' => $command->orderItemId,
                'opened_by_user_id' => $command->openedByUserId,
                'status' => DisputeCaseStatus::Opened,
                'resolution_outcome' => null,
                'opened_at' => now(),
                'resolved_at' => null,
                'resolution_notes' => null,
            ]);

            $this->orderService->markOrderDisputed(new MarkOrderDisputedCommand(
                orderId: $order->id,
                actorUserId: $command->openedByUserId,
                correlationId: $command->correlationId,
            ));

            $this->escrowService->markUnderDispute(new MarkEscrowUnderDisputeCommand(
                escrowAccountId: $escrow->id,
                disputeCaseId: $case->id,
            ));

            if ($idem !== null) {
                $this->markDisputeIdempotencySucceeded($idem['idempotency'], [
                    'dispute_case_id' => $case->id,
                    'order_id' => $order->id,
                    'escrow_account_id' => $escrow->id,
                ]);
            }

            return [
                'dispute_case_id' => $case->id,
                'order_id' => $order->id,
                'escrow_account_id' => $escrow->id,
                'status' => $case->status->value,
                'idempotent_replay' => false,
            ];
        });
    }

    public function submitEvidence(SubmitDisputeEvidenceCommand $command): array
    {
        if ($command->evidence === []) {
            throw new DisputeResolutionConflictException($command->disputeCaseId, 'evidence_required');
        }

        return DB::transaction(function () use ($command): array {
            $case = DisputeCase::query()->whereKey($command->disputeCaseId)->lockForUpdate()->first();
            if ($case === null) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'dispute_case_not_found');
            }

            if ($case->status === DisputeCaseStatus::Resolved) {
                throw new InvalidDisputeStateTransitionException(
                    disputeCaseId: $case->id,
                    fromStatus: $case->status->value,
                    toStatus: DisputeCaseStatus::EvidenceCollection->value,
                );
            }

            if (! in_array($case->status, [DisputeCaseStatus::Opened, DisputeCaseStatus::EvidenceCollection], true)) {
                throw new InvalidDisputeStateTransitionException(
                    disputeCaseId: $case->id,
                    fromStatus: $case->status->value,
                    toStatus: DisputeCaseStatus::EvidenceCollection->value,
                );
            }

            $inserted = 0;
            foreach ($command->evidence as $item) {
                if (! in_array($item->evidenceType, self::EVIDENCE_TYPES, true)) {
                    throw new DisputeResolutionConflictException($case->id, 'invalid_evidence_type');
                }

                DisputeEvidence::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'dispute_case_id' => $case->id,
                    'order_id' => $case->order_id,
                    'submitted_by_user_id' => $command->submittedByUserId,
                    'evidence_type' => $item->evidenceType,
                    'content_text' => $item->contentText,
                    'storage_path' => $item->storagePath,
                    'checksum_sha256' => $item->checksumSha256,
                    'submitted_at' => now(),
                ]);
                ++$inserted;
            }

            if ($case->status === DisputeCaseStatus::Opened) {
                $case->status = DisputeCaseStatus::EvidenceCollection;
                $case->save();
            }

            return [
                'dispute_case_id' => $case->id,
                'status' => $case->status->value,
                'evidence_rows_inserted' => $inserted,
            ];
        });
    }

    public function moveToReview(MoveDisputeToReviewCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $case = DisputeCase::query()->whereKey($command->disputeCaseId)->lockForUpdate()->first();
            if ($case === null) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'dispute_case_not_found');
            }

            if ($case->status === DisputeCaseStatus::Resolved) {
                throw new InvalidDisputeStateTransitionException(
                    disputeCaseId: $case->id,
                    fromStatus: $case->status->value,
                    toStatus: DisputeCaseStatus::UnderReview->value,
                );
            }

            if (! in_array($case->status, [DisputeCaseStatus::Opened, DisputeCaseStatus::EvidenceCollection], true)) {
                throw new InvalidDisputeStateTransitionException(
                    disputeCaseId: $case->id,
                    fromStatus: $case->status->value,
                    toStatus: DisputeCaseStatus::UnderReview->value,
                );
            }

            $case->status = DisputeCaseStatus::UnderReview;
            $case->save();

            return [
                'dispute_case_id' => $case->id,
                'status' => $case->status->value,
            ];
        });
    }

    public function escalateDispute(EscalateDisputeCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $case = DisputeCase::query()->whereKey($command->disputeCaseId)->lockForUpdate()->first();
            if ($case === null) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'dispute_case_not_found');
            }

            if ($case->status === DisputeCaseStatus::Resolved) {
                throw new InvalidDisputeStateTransitionException(
                    disputeCaseId: $case->id,
                    fromStatus: $case->status->value,
                    toStatus: DisputeCaseStatus::Escalated->value,
                );
            }

            if ($case->status !== DisputeCaseStatus::UnderReview) {
                throw new InvalidDisputeStateTransitionException(
                    disputeCaseId: $case->id,
                    fromStatus: $case->status->value,
                    toStatus: DisputeCaseStatus::Escalated->value,
                );
            }

            $case->status = DisputeCaseStatus::Escalated;
            $case->save();

            return [
                'dispute_case_id' => $case->id,
                'status' => $case->status->value,
            ];
        });
    }

    public function resolveDispute(ResolveDisputeCommand $command): array
    {
        if (trim($command->notes) === '') {
            throw new DisputeResolutionConflictException($command->disputeCaseId, 'decision_notes_required');
        }
        if (trim($command->idempotencyKey) === '') {
            throw new DisputeResolutionConflictException($command->disputeCaseId, 'idempotency_key_required');
        }

        return DB::transaction(function () use ($command): array {
            $case = DisputeCase::query()->whereKey($command->disputeCaseId)->lockForUpdate()->first();
            if ($case === null) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'dispute_case_not_found');
            }

            $existingDecision = DisputeDecision::query()
                ->where('dispute_case_id', $case->id)
                ->lockForUpdate()
                ->first();

            if ($case->status === DisputeCaseStatus::Resolved) {
                if ($existingDecision === null) {
                    throw new DisputeResolutionConflictException($case->id, 'dispute_resolved_without_decision');
                }
                if ($this->decisionMatchesCommand($existingDecision, $command)) {
                    return $this->buildResolveReplayPayload($case, $existingDecision);
                }

                throw new DisputeResolutionConflictException($case->id, 'dispute_already_resolved_differently');
            }

            if (! in_array($case->status, [DisputeCaseStatus::UnderReview, DisputeCaseStatus::Escalated], true)) {
                throw new InvalidDisputeStateTransitionException(
                    disputeCaseId: $case->id,
                    fromStatus: $case->status->value,
                    toStatus: DisputeCaseStatus::Resolved->value,
                );
            }

            $requestHash = $this->hashPayload([
                'dispute_case_id' => $command->disputeCaseId,
                'decided_by_user_id' => $command->decidedByUserId,
                'outcome' => $command->outcome->value,
                'buyer_amount' => $command->buyerAmount,
                'seller_amount' => $command->sellerAmount,
                'currency' => $command->currency,
                'reason_code' => $command->reasonCode,
                'notes' => $command->notes,
                'allocate_buyer' => $command->allocateBuyerFullRemaining,
                'allocate_seller' => $command->allocateSellerFullRemaining,
                'partial_buyer' => $command->partialBuyerRefundAmount,
            ]);
            $idem = $this->claimDisputeIdempotency('dispute_resolve', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                $case->refresh();
                $decision = DisputeDecision::query()
                    ->where('dispute_case_id', $case->id)
                    ->lockForUpdate()
                    ->first();
                if ($decision === null || $case->status !== DisputeCaseStatus::Resolved) {
                    throw new IdempotencyConflictException(
                        $command->idempotencyKey,
                        'dispute_resolve',
                    );
                }

                return $this->buildResolveReplayPayload($case, $decision);
            }

            $escrow = EscrowAccount::query()
                ->where('order_id', $case->order_id)
                ->lockForUpdate()
                ->first();
            if ($escrow === null) {
                throw new DisputeResolutionConflictException($case->id, 'escrow_not_found_for_order');
            }
            if ($escrow->state !== EscrowState::UnderDispute) {
                throw new DisputeResolutionConflictException($case->id, 'escrow_not_under_dispute');
            }

            $remainingScale = $this->remainingEscrowScale($escrow);
            if ($remainingScale <= 0) {
                throw new DisputeResolutionConflictException($case->id, 'no_escrow_remaining_to_settle');
            }
            $remainingDecimal = $this->fromScale($remainingScale);

            [$buyerAmt, $sellerAmt] = $this->resolveSettlementAmounts($command, $escrow, $remainingScale, $remainingDecimal);

            if ((string) $escrow->currency !== $command->currency) {
                throw new DisputeResolutionConflictException($case->id, 'currency_mismatch_escrow');
            }

            $this->assertOutcomeAmountsConsistent($case->id, $command->outcome, $buyerAmt, $sellerAmt, $remainingDecimal);

            $settle = $this->escrowService->settleEscrowFromDispute(new SettleEscrowFromDisputeCommand(
                escrowAccountId: $escrow->id,
                disputeCaseId: $case->id,
                buyerRefundAmount: $buyerAmt,
                sellerReleaseAmount: $sellerAmt,
                idempotencyKey: $command->idempotencyKey.':escrow_settle',
            ));

            $decision = DisputeDecision::query()->create([
                'uuid' => (string) Str::uuid(),
                'dispute_case_id' => $case->id,
                'decided_by_user_id' => $command->decidedByUserId,
                'outcome' => $command->outcome,
                'buyer_amount' => $buyerAmt,
                'seller_amount' => $sellerAmt,
                'currency' => $command->currency,
                'reason_code' => $command->reasonCode,
                'notes' => $command->notes,
                'escrow_event_id' => $settle['escrow_event_id'] ?? null,
                'ledger_batch_id' => $settle['ledger_batch_id'] ?? null,
                'decided_at' => now(),
            ]);

            $case->status = DisputeCaseStatus::Resolved;
            $case->resolution_outcome = $command->outcome;
            $case->resolved_at = now();
            $case->resolution_notes = $command->resolutionNotes;
            $case->save();

            $targetOrderStatus = $this->toScale($sellerAmt) > 0
                ? OrderStatus::Completed
                : OrderStatus::Refunded;

            $this->orderService->applyOrderStatusAfterDisputeResolution(
                new ApplyOrderStatusAfterDisputeResolutionCommand(
                    orderId: $case->order_id,
                    targetStatus: $targetOrderStatus,
                    actorUserId: $command->decidedByUserId,
                    reasonCode: 'dispute_resolved:'.$command->outcome->value,
                    correlationId: null,
                ),
            );

            $this->markDisputeIdempotencySucceeded($idem['idempotency'], [
                'dispute_case_id' => $case->id,
                'dispute_decision_id' => $decision->id,
                'escrow_account_id' => $escrow->id,
                'order_status' => $targetOrderStatus->value,
            ]);

            $order = Order::query()->whereKey($case->order_id)->firstOrFail();

            return [
                'dispute_case_id' => $case->id,
                'dispute_decision_id' => $decision->id,
                'status' => $case->status->value,
                'resolution_outcome' => $case->resolution_outcome->value,
                'escrow_account_id' => $escrow->id,
                'escrow_state' => $settle['state'],
                'order_status' => $order->status->value,
                'idempotent_replay' => false,
            ];
        });
    }

    public function resolveDisputeRefund(
        int $disputeCaseId,
        int $decidedByUserId,
        string $currency,
        string $reasonCode,
        string $notes,
        string $idempotencyKey,
        ?string $resolutionNotes = null,
    ): array {
        return $this->resolveDispute(new ResolveDisputeCommand(
            disputeCaseId: $disputeCaseId,
            decidedByUserId: $decidedByUserId,
            outcome: DisputeResolutionOutcome::BuyerWins,
            buyerAmount: '0.0000',
            sellerAmount: '0.0000',
            currency: $currency,
            reasonCode: $reasonCode,
            notes: $notes,
            idempotencyKey: $idempotencyKey,
            resolutionNotes: $resolutionNotes,
            allocateBuyerFullRemaining: true,
        ));
    }

    public function resolveDisputeRelease(
        int $disputeCaseId,
        int $decidedByUserId,
        string $currency,
        string $reasonCode,
        string $notes,
        string $idempotencyKey,
        ?string $resolutionNotes = null,
    ): array {
        return $this->resolveDispute(new ResolveDisputeCommand(
            disputeCaseId: $disputeCaseId,
            decidedByUserId: $decidedByUserId,
            outcome: DisputeResolutionOutcome::SellerWins,
            buyerAmount: '0.0000',
            sellerAmount: '0.0000',
            currency: $currency,
            reasonCode: $reasonCode,
            notes: $notes,
            idempotencyKey: $idempotencyKey,
            resolutionNotes: $resolutionNotes,
            allocateSellerFullRemaining: true,
        ));
    }

    public function resolveDisputePartialRefund(
        int $disputeCaseId,
        int $decidedByUserId,
        string $buyerRefundAmount,
        string $currency,
        string $reasonCode,
        string $notes,
        string $idempotencyKey,
        ?string $resolutionNotes = null,
    ): array {
        return $this->resolveDispute(new ResolveDisputeCommand(
            disputeCaseId: $disputeCaseId,
            decidedByUserId: $decidedByUserId,
            outcome: DisputeResolutionOutcome::SplitDecision,
            buyerAmount: '0.0000',
            sellerAmount: '0.0000',
            currency: $currency,
            reasonCode: $reasonCode,
            notes: $notes,
            idempotencyKey: $idempotencyKey,
            resolutionNotes: $resolutionNotes,
            partialBuyerRefundAmount: $buyerRefundAmount,
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSettlementAmounts(
        ResolveDisputeCommand $command,
        EscrowAccount $escrow,
        int $remainingScale,
        string $remainingDecimal,
    ): array {
        if ($command->allocateBuyerFullRemaining) {
            if ($command->outcome !== DisputeResolutionOutcome::BuyerWins) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'outcome_must_be_buyer_wins_for_full_refund');
            }

            return [$remainingDecimal, '0.0000'];
        }

        if ($command->allocateSellerFullRemaining) {
            if ($command->outcome !== DisputeResolutionOutcome::SellerWins) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'outcome_must_be_seller_wins_for_full_release');
            }

            return ['0.0000', $remainingDecimal];
        }

        if ($command->partialBuyerRefundAmount !== null) {
            if ($command->outcome !== DisputeResolutionOutcome::SplitDecision) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'outcome_must_be_split_for_partial_refund');
            }
            $bScale = $this->toScale($command->partialBuyerRefundAmount);
            if ($bScale <= 0 || $bScale >= $remainingScale) {
                throw new DisputeResolutionConflictException($command->disputeCaseId, 'invalid_partial_buyer_refund_amount');
            }
            $buyer = $this->fromScale($bScale);
            $seller = $this->fromScale($remainingScale - $bScale);

            return [$buyer, $seller];
        }

        return [$command->buyerAmount, $command->sellerAmount];
    }

    private function assertOutcomeAmountsConsistent(
        int $disputeCaseId,
        DisputeResolutionOutcome $outcome,
        string $buyerAmt,
        string $sellerAmt,
        string $remainingDecimal,
    ): void {
        $b = $this->toScale($buyerAmt);
        $s = $this->toScale($sellerAmt);
        $r = $this->toScale($remainingDecimal);
        if ($b + $s !== $r) {
            throw new DisputeResolutionConflictException($disputeCaseId, 'settlement_amounts_must_equal_remaining_escrow');
        }

        match ($outcome) {
            DisputeResolutionOutcome::BuyerWins => $s === 0 && $b === $r
                ? true
                : throw new DisputeResolutionConflictException($disputeCaseId, 'buyer_wins_requires_full_buyer_allocation'),
            DisputeResolutionOutcome::SellerWins => $b === 0 && $s === $r
                ? true
                : throw new DisputeResolutionConflictException($disputeCaseId, 'seller_wins_requires_full_seller_allocation'),
            DisputeResolutionOutcome::SplitDecision => ($b > 0 && $s > 0)
                ? true
                : throw new DisputeResolutionConflictException($disputeCaseId, 'split_decision_requires_positive_buyer_and_seller_shares'),
        };
    }

    private function decisionMatchesCommand(DisputeDecision $d, ResolveDisputeCommand $c): bool
    {
        if ($d->outcome !== $c->outcome
            || (string) $d->currency !== $c->currency
            || (string) $d->reason_code !== $c->reasonCode
            || (string) $d->notes !== $c->notes) {
            return false;
        }

        if ($c->allocateBuyerFullRemaining) {
            return $d->outcome === DisputeResolutionOutcome::BuyerWins
                && $this->toScale((string) $d->seller_amount) === 0;
        }

        if ($c->allocateSellerFullRemaining) {
            return $d->outcome === DisputeResolutionOutcome::SellerWins
                && $this->toScale((string) $d->buyer_amount) === 0;
        }

        if ($c->partialBuyerRefundAmount !== null) {
            return $d->outcome === DisputeResolutionOutcome::SplitDecision
                && $this->toScale((string) $d->buyer_amount) === $this->toScale($c->partialBuyerRefundAmount);
        }

        return $this->toScale((string) $d->buyer_amount) === $this->toScale($c->buyerAmount)
            && $this->toScale((string) $d->seller_amount) === $this->toScale($c->sellerAmount);
    }

    private function buildResolveReplayPayload(DisputeCase $case, DisputeDecision $decision): array
    {
        $escrow = EscrowAccount::query()->where('order_id', $case->order_id)->first();
        $order = Order::query()->whereKey($case->order_id)->first();

        return [
            'dispute_case_id' => $case->id,
            'dispute_decision_id' => $decision->id,
            'status' => DisputeCaseStatus::Resolved->value,
            'resolution_outcome' => $case->resolution_outcome?->value,
            'escrow_account_id' => $escrow !== null ? (int) $escrow->id : null,
            'escrow_state' => $escrow !== null ? $escrow->state->value : null,
            'order_status' => $order !== null ? $order->status->value : null,
            'idempotent_replay' => true,
        ];
    }

    private function findReplayableOpenDispute(OpenDisputeCommand $command): ?DisputeCase
    {
        return DisputeCase::query()
            ->where('order_id', $command->orderId)
            ->where('opened_by_user_id', $command->openedByUserId)
            ->where('status', '!=', DisputeCaseStatus::Resolved->value)
            ->when(
                $command->orderItemId !== null,
                static fn ($q) => $q->where('order_item_id', $command->orderItemId),
                static fn ($q) => $q->whereNull('order_item_id'),
            )
            ->orderByDesc('id')
            ->first();
    }

    private function hasActiveDisputeOnOrder(int $orderId): bool
    {
        return DisputeCase::query()
            ->where('order_id', $orderId)
            ->where('status', '!=', DisputeCaseStatus::Resolved->value)
            ->exists();
    }

    private function remainingEscrowScale(EscrowAccount $escrow): int
    {
        $held = $this->toScale((string) $escrow->held_amount);
        $released = $this->toScale((string) $escrow->released_amount);
        $refunded = $this->toScale((string) $escrow->refunded_amount);
        $remaining = $held - ($released + $refunded);
        if ($remaining < 0) {
            throw new DisputeResolutionConflictException(0, 'escrow_conservation_violation');
        }

        return $remaining;
    }

    private function toScale(string $amount): int
    {
        $normalized = trim($amount);
        if (! preg_match('/^-?\d+(\.\d{1,4})?$/', $normalized)) {
            throw new DisputeResolutionConflictException(0, 'invalid_decimal_precision');
        }

        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad($fraction, 4, '0');
        $scaled = ((int) $whole * 10000) + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }

    private function fromScale(int $scaled): string
    {
        $negative = $scaled < 0;
        $absolute = abs($scaled);
        $whole = intdiv($absolute, 10000);
        $fraction = $absolute % 10000;

        $value = sprintf('%d.%04d', $whole, $fraction);

        return $negative ? '-'.$value : $value;
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{idempotency: IdempotencyKey, replay: bool}
     */
    private function claimDisputeIdempotency(string $scope, string $key, string $requestHash): array
    {
        $existing = IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            if ($existing->request_hash !== $requestHash) {
                throw new IdempotencyConflictException($key, $scope);
            }
            if ($existing->status === IdempotencyKeyStatus::Succeeded) {
                return ['idempotency' => $existing, 'replay' => true];
            }

            throw new IdempotencyConflictException($key, $scope);
        }

        try {
            $created = IdempotencyKey::query()->create([
                'key' => $key,
                'scope' => $scope,
                'request_hash' => $requestHash,
                'status' => IdempotencyKeyStatus::Started,
                'expires_at' => now()->addDay(),
            ]);
        } catch (QueryException $e) {
            $raced = IdempotencyKey::query()
                ->where('scope', $scope)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($raced !== null && $raced->request_hash === $requestHash && $raced->status === IdempotencyKeyStatus::Succeeded) {
                return ['idempotency' => $raced, 'replay' => true];
            }

            throw new IdempotencyConflictException($key, $scope, previous: $e);
        }

        return ['idempotency' => $created, 'replay' => false];
    }

    private function markDisputeIdempotencySucceeded(IdempotencyKey $key, array $response): void
    {
        $key->status = IdempotencyKeyStatus::Succeeded;
        $key->response_hash = hash('sha256', json_encode($response, JSON_THROW_ON_ERROR));
        $key->save();
    }
}

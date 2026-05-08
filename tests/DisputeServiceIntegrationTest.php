<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Commands\Dispute\EscalateDisputeCommand;
use App\Domain\Commands\Dispute\MoveDisputeToReviewCommand;
use App\Domain\Commands\Dispute\OpenDisputeCommand;
use App\Domain\Commands\Dispute\ResolveDisputeCommand;
use App\Domain\Commands\Dispute\SubmitDisputeEvidenceCommand;
use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\Escrow\ReleaseEscrowCommand;
use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\DisputeResolutionOutcome;
use App\Domain\Enums\EscrowEventType;
use App\Domain\Enums\EscrowState;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletHoldStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\DisputeResolutionConflictException;
use App\Domain\Exceptions\EscrowReleaseConflictException;
use App\Domain\Exceptions\InvalidDisputeStateTransitionException;
use App\Domain\Exceptions\InvalidEscrowStateTransitionException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Value\DisputeEvidenceItem;
use App\Domain\Value\LedgerPostingLine;
use App\Models\DisputeCase;
use App\Models\DisputeDecision;
use App\Models\EscrowAccount;
use App\Models\EscrowEvent;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Services\Dispute\DisputeService;
use App\Services\Escrow\EscrowService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Support\Str;

final class DisputeServiceIntegrationTest extends TestCase
{
    private WalletLedgerService $wallet;

    private EscrowService $escrow;

    private DisputeService $disputes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = new WalletLedgerService();
        $this->escrow = new EscrowService($this->wallet);
        $this->disputes = new DisputeService($this->wallet, $this->escrow);
    }

    /** @covers DisputeService::openDispute */
    public function test_open_dispute_persists_case_and_updates_order_escrow_and_wallet_holds(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('40.0000');

        $beforeCases = DisputeCase::query()->count();
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'item_not_received',
            idempotencyKey: 'open-dispute-1',
        ));

        self::assertSame($beforeCases + 1, DisputeCase::query()->count());
        $case = DisputeCase::query()->findOrFail((int) $open['dispute_case_id']);
        self::assertSame(DisputeCaseStatus::Opened, $case->status);
        self::assertNull($case->resolution_outcome);
        self::assertSame($buyerId, $case->opened_by_user_id);
        self::assertSame($order->id, $case->order_id);

        self::assertSame(OrderStatus::Disputed, Order::query()->findOrFail($order->id)->status);
        self::assertSame(EscrowState::UnderDispute, EscrowAccount::query()->findOrFail($escrowId)->state);

        $disputeOpened = EscrowEvent::query()
            ->where('escrow_account_id', $escrowId)
            ->where('event_type', EscrowEventType::DisputeOpened)
            ->firstOrFail();
        self::assertSame($case->id, (int) $disputeOpened->reference_id);

        $buyerBal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        self::assertSame('160.0000', (string) $buyerBal['available_balance']);
        self::assertSame('40.0000', (string) $buyerBal['held_balance']);

        $hold = WalletHold::query()
            ->where('reference_type', 'escrow_account')
            ->where('reference_id', $escrowId)
            ->firstOrFail();
        self::assertSame(WalletHoldStatus::Active, $hold->status);
        self::assertSame($buyerWalletId, (int) $hold->wallet_id);

        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId));
    }

    /** @covers dispute freeze via EscrowService + DisputeService orchestration */
    public function test_dispute_freeze_blocks_release_and_leaves_ledger_escrow_unchanged(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('25.0000');
        $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'quality',
        ));

        $entriesBefore = WalletLedgerEntry::query()->count();
        $eventsBefore = EscrowEvent::query()->where('escrow_account_id', $escrowId)->count();
        $releaseIdemBefore = IdempotencyKey::query()->where('scope', 'escrow_release')->count();

        try {
            $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowId, 'try-release-under-dispute'));
            self::fail('Expected EscrowReleaseConflictException');
        } catch (EscrowReleaseConflictException $e) {
            self::assertSame('escrow_frozen_under_dispute', $e->reasonCode);
        }

        self::assertSame(EscrowState::UnderDispute, EscrowAccount::query()->findOrFail($escrowId)->state);
        self::assertSame($entriesBefore, WalletLedgerEntry::query()->count());
        self::assertSame($eventsBefore, EscrowEvent::query()->where('escrow_account_id', $escrowId)->count());
        self::assertSame($releaseIdemBefore, IdempotencyKey::query()->where('scope', 'escrow_release')->count());
        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId));
    }

    /** @covers DisputeService::resolveDisputeRefund */
    public function test_full_refund_resolution_sets_dispute_escrow_order_and_buyer_wallet(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('50.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'not_as_described',
        ));
        $caseId = (int) $open['dispute_case_id'];
        $this->disputes->moveToReview(new MoveDisputeToReviewCommand($caseId));

        $ledgerBefore = WalletLedgerEntry::query()->count();

        $out = $this->disputes->resolveDisputeRefund(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            currency: 'USD',
            reasonCode: 'admin_refund',
            notes: 'Full refund to buyer per review.',
            idempotencyKey: 'resolve-refund-full-1',
        );

        self::assertFalse((bool) ($out['idempotent_replay'] ?? false));
        self::assertSame('refunded', $out['escrow_state']);
        self::assertSame(OrderStatus::Refunded->value, $out['order_status']);

        $case = DisputeCase::query()->findOrFail($caseId);
        self::assertSame(DisputeCaseStatus::Resolved, $case->status);
        self::assertSame(DisputeResolutionOutcome::BuyerWins, $case->resolution_outcome);
        self::assertNotNull($case->resolved_at);

        $decision = DisputeDecision::query()->where('dispute_case_id', $caseId)->firstOrFail();
        self::assertSame(DisputeResolutionOutcome::BuyerWins, $decision->outcome);
        self::assertSame('50.0000', (string) $decision->buyer_amount);
        self::assertSame('0.0000', (string) $decision->seller_amount);

        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::Refunded, $escrow->state);
        self::assertSame('50.0000', (string) $escrow->refunded_amount);
        self::assertNotNull($escrow->closed_at);
        $this->assertEscrowConservation($escrow, EscrowState::Refunded);

        $buyerBal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        self::assertSame('200.0000', (string) $buyerBal['available_balance']);
        self::assertSame('0.0000', (string) $buyerBal['held_balance']);

        $sellerBal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($sellerWalletId));
        self::assertSame('0.0000', (string) $sellerBal['available_balance']);

        self::assertGreaterThan($ledgerBefore, WalletLedgerEntry::query()->count());
        self::assertNotNull(
            WalletLedgerEntry::query()
                ->where('wallet_id', $buyerWalletId)
                ->where('entry_type', WalletLedgerEntryType::RefundCredit)
                ->where('reference_type', 'dispute_case')
                ->where('reference_id', $caseId)
                ->first()
        );

        $hold = WalletHold::query()
            ->where('reference_type', 'escrow_account')
            ->where('reference_id', $escrowId)
            ->firstOrFail();
        self::assertSame(WalletHoldStatus::Consumed, $hold->status);

        EscrowEvent::query()
            ->where('escrow_account_id', $escrowId)
            ->where('event_type', EscrowEventType::DisputeResolved)
            ->firstOrFail();
    }

    /** @covers DisputeService::resolveDisputePartialRefund */
    public function test_partial_split_refund_resolution_updates_escrow_balances_and_ledger(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('100.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'partial_agreement',
        ));
        $caseId = (int) $open['dispute_case_id'];
        $this->disputes->moveToReview(new MoveDisputeToReviewCommand($caseId));

        $out = $this->disputes->resolveDisputePartialRefund(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            buyerRefundAmount: '40.0000',
            currency: 'USD',
            reasonCode: 'split_60_40',
            notes: 'Partial refund; remainder to seller.',
            idempotencyKey: 'resolve-split-1',
        );

        self::assertSame('released', $out['escrow_state']);
        self::assertSame(OrderStatus::Completed->value, $out['order_status']);

        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame('40.0000', (string) $escrow->refunded_amount);
        self::assertSame('60.0000', (string) $escrow->released_amount);
        self::assertSame(EscrowState::Released, $escrow->state);
        $this->assertEscrowConservation($escrow, EscrowState::Released);

        $decision = DisputeDecision::query()->where('dispute_case_id', $caseId)->firstOrFail();
        self::assertSame(DisputeResolutionOutcome::SplitDecision, $decision->outcome);
        self::assertSame('40.0000', (string) $decision->buyer_amount);
        self::assertSame('60.0000', (string) $decision->seller_amount);

        self::assertSame(2, WalletLedgerEntry::query()
            ->where('reference_type', 'dispute_case')
            ->where('reference_id', $caseId)
            ->count());
        self::assertNotNull(
            WalletLedgerEntry::query()
                ->where('wallet_id', $buyerWalletId)
                ->where('entry_type', WalletLedgerEntryType::RefundCredit)
                ->where('reference_type', 'dispute_case')
                ->where('reference_id', $caseId)
                ->first()
        );
        self::assertNotNull(
            WalletLedgerEntry::query()
                ->where('wallet_id', $sellerWalletId)
                ->where('entry_type', WalletLedgerEntryType::EscrowReleaseCredit)
                ->where('reference_type', 'dispute_case')
                ->where('reference_id', $caseId)
                ->first()
        );

        $buyerBal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        self::assertSame('140.0000', (string) $buyerBal['available_balance']);
        self::assertSame('0.0000', (string) $buyerBal['held_balance']);

        $sellerBal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($sellerWalletId));
        self::assertSame('60.0000', (string) $sellerBal['available_balance']);
    }

    /** @covers DisputeService::resolveDisputeRelease */
    public function test_release_to_seller_resolution_credits_seller_and_closes_escrow(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('35.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'buyer_fault',
        ));
        $caseId = (int) $open['dispute_case_id'];
        $this->disputes->moveToReview(new MoveDisputeToReviewCommand($caseId));

        $out = $this->disputes->resolveDisputeRelease(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            currency: 'USD',
            reasonCode: 'seller_prevails',
            notes: 'Release escrow to seller.',
            idempotencyKey: 'resolve-release-seller-1',
        );

        self::assertSame('released', $out['escrow_state']);
        self::assertSame(OrderStatus::Completed->value, $out['order_status']);
        self::assertSame(EscrowState::Released, EscrowAccount::query()->findOrFail($escrowId)->state);

        $decision = DisputeDecision::query()->where('dispute_case_id', $caseId)->firstOrFail();
        self::assertSame(DisputeResolutionOutcome::SellerWins, $decision->outcome);
        self::assertSame('0.0000', (string) $decision->buyer_amount);
        self::assertSame('35.0000', (string) $decision->seller_amount);

        $sellerBal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($sellerWalletId));
        self::assertSame('35.0000', (string) $sellerBal['available_balance']);

        $buyerBal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        self::assertSame('165.0000', (string) $buyerBal['available_balance']);
        self::assertSame('0.0000', (string) $buyerBal['held_balance']);

        self::assertNotNull(
            WalletLedgerEntry::query()
                ->where('wallet_id', $sellerWalletId)
                ->where('entry_type', WalletLedgerEntryType::EscrowReleaseCredit)
                ->where('reference_type', 'dispute_case')
                ->where('reference_id', $caseId)
                ->first()
        );
    }

    public function test_invalid_duplicate_open_rejected_when_active_dispute_exists(): void
    {
        [$order, $buyerId] = $this->seedPaidInEscrowOrderWithHeldEscrow('40.0000');

        $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'first',
        ));

        $casesBefore = DisputeCase::query()->count();

        try {
            $this->disputes->openDispute(new OpenDisputeCommand(
                orderId: $order->id,
                orderItemId: null,
                openedByUserId: $buyerId,
                reasonCode: 'second_attempt',
            ));
            self::fail('Expected DisputeResolutionConflictException');
        } catch (DisputeResolutionConflictException) {
            self::assertSame($casesBefore, DisputeCase::query()->count());
        }
    }

    public function test_open_dispute_idempotent_replay_with_same_key(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('22.0000');
        $key = 'idem-open-'.Str::random(6);

        $a = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'duplicate_key',
            idempotencyKey: $key,
        ));
        $b = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'duplicate_key',
            idempotencyKey: $key,
        ));

        self::assertTrue((bool) $b['idempotent_replay']);
        self::assertSame((int) $a['dispute_case_id'], (int) $b['dispute_case_id']);
        self::assertSame(1, DisputeCase::query()->where('order_id', $order->id)->count());
    }

    public function test_terminal_second_escrow_release_throws_after_dispute_refund_resolution(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('30.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'refund_then_release',
        ));
        $caseId = (int) $open['dispute_case_id'];
        $this->disputes->moveToReview(new MoveDisputeToReviewCommand($caseId));
        $this->disputes->resolveDisputeRefund(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            currency: 'USD',
            reasonCode: 'refund',
            notes: 'Terminal refund.',
            idempotencyKey: 'term-refund-1',
        );

        self::assertSame(EscrowState::Refunded, EscrowAccount::query()->findOrFail($escrowId)->state);

        $this->expectException(InvalidEscrowStateTransitionException::class);
        $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowId, 'late-release-after-refund'));
    }

    public function test_resolve_rollback_no_decision_or_ledger_when_dispute_not_in_review(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('18.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'skip_review',
        ));
        $caseId = (int) $open['dispute_case_id'];

        $decisionsBefore = DisputeDecision::query()->count();
        $ledgerBefore = WalletLedgerEntry::query()->count();

        $this->expectException(InvalidDisputeStateTransitionException::class);
        $this->disputes->resolveDisputeRefund(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            currency: 'USD',
            reasonCode: 'too_early',
            notes: 'Cannot resolve from opened.',
            idempotencyKey: 'rollback-state-1',
        );

        self::assertSame($decisionsBefore, DisputeDecision::query()->count());
        self::assertSame($ledgerBefore, WalletLedgerEntry::query()->count());
        self::assertSame(EscrowState::UnderDispute, EscrowAccount::query()->findOrFail($escrowId)->state);
        self::assertSame(OrderStatus::Disputed, Order::query()->findOrFail($order->id)->status);
    }

    public function test_resolve_rollback_on_currency_mismatch_leaves_escrow_and_no_decision(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('20.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'bad_currency',
        ));
        $caseId = (int) $open['dispute_case_id'];
        $this->disputes->moveToReview(new MoveDisputeToReviewCommand($caseId));

        $decisionsBefore = DisputeDecision::query()->count();
        $ledgerBefore = WalletLedgerEntry::query()->count();

        $this->expectException(DisputeResolutionConflictException::class);
        $this->disputes->resolveDispute(new ResolveDisputeCommand(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            outcome: DisputeResolutionOutcome::BuyerWins,
            buyerAmount: '20.0000',
            sellerAmount: '0.0000',
            currency: 'EUR',
            reasonCode: 'wrong_ccy',
            notes: 'Mismatch currency on purpose.',
            idempotencyKey: 'rollback-ccy-1',
            allocateBuyerFullRemaining: false,
        ));

        self::assertSame($decisionsBefore, DisputeDecision::query()->count());
        self::assertSame($ledgerBefore, WalletLedgerEntry::query()->count());
        self::assertSame(EscrowState::UnderDispute, EscrowAccount::query()->findOrFail($escrowId)->state);
        self::assertSame(DisputeCaseStatus::UnderReview, DisputeCase::query()->findOrFail($caseId)->status);
    }

    public function test_idempotent_resolution_replay_same_payload_no_duplicate_ledger(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('45.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'idem_resolve',
        ));
        $caseId = (int) $open['dispute_case_id'];
        $this->disputes->moveToReview(new MoveDisputeToReviewCommand($caseId));

        $idemKey = 'idem-resolve-'.Str::random(8);
        $notes = 'Same notes for idempotency.';

        $r1 = $this->disputes->resolveDisputeRefund(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            currency: 'USD',
            reasonCode: 'refund_idem',
            notes: $notes,
            idempotencyKey: $idemKey,
        );
        $decisionId = (int) $r1['dispute_decision_id'];
        $entriesAfterFirst = WalletLedgerEntry::query()->count();

        $r2 = $this->disputes->resolveDisputeRefund(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            currency: 'USD',
            reasonCode: 'refund_idem',
            notes: $notes,
            idempotencyKey: $idemKey,
        );

        self::assertTrue((bool) $r2['idempotent_replay']);
        self::assertSame($decisionId, (int) $r2['dispute_decision_id']);
        self::assertSame($entriesAfterFirst, WalletLedgerEntry::query()->count());
        self::assertSame(1, DisputeDecision::query()->where('dispute_case_id', $caseId)->count());
    }

    public function test_escalate_from_under_review_then_resolve(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('15.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'escalation_path',
        ));
        $caseId = (int) $open['dispute_case_id'];
        $this->disputes->moveToReview(new MoveDisputeToReviewCommand($caseId));
        $esc = $this->disputes->escalateDispute(new EscalateDisputeCommand($caseId));
        self::assertSame(DisputeCaseStatus::Escalated->value, $esc['status']);

        $out = $this->disputes->resolveDisputeRefund(
            disputeCaseId: $caseId,
            decidedByUserId: $buyerId,
            currency: 'USD',
            reasonCode: 'post_escalation_refund',
            notes: 'Refund after escalation.',
            idempotencyKey: 'resolve-after-escalate',
        );
        self::assertSame('refunded', $out['escrow_state']);
    }

    public function test_open_dispute_rejects_unfunded_order(): void
    {
        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'b-'.Str::random(6).'@example.test',
            'password_hash' => 'x',
        ]);
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::PendingPayment,
            'currency' => 'USD',
            'gross_amount' => '10.0000',
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => '10.0000',
            'placed_at' => now(),
        ]);

        $this->expectException(InvalidOrderStateTransitionException::class);
        $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyer->id,
            reasonCode: 'x',
        ));
    }

    public function test_submit_evidence_transitions_to_evidence_collection(): void
    {
        [$order, $buyerId, $escrowId, $buyerWalletId, $sellerWalletId] = $this->seedPaidInEscrowOrderWithHeldEscrow('10.0000');
        $open = $this->disputes->openDispute(new OpenDisputeCommand(
            orderId: $order->id,
            orderItemId: null,
            openedByUserId: $buyerId,
            reasonCode: 'evidence',
        ));
        $caseId = (int) $open['dispute_case_id'];

        $ev = $this->disputes->submitEvidence(SubmitDisputeEvidenceCommand::fromItems(
            $caseId,
            $buyerId,
            new DisputeEvidenceItem('text', 'Buyer statement.', null, null),
        ));

        self::assertSame(DisputeCaseStatus::EvidenceCollection->value, $ev['status']);
        self::assertSame(1, (int) $ev['evidence_rows_inserted']);
    }

    /**
     * @return array{0: Order, 1: int, 2: int, 3: int, 4: int} order, buyer user id, escrow id, buyer wallet id, seller wallet id
     */
    private function seedPaidInEscrowOrderWithHeldEscrow(string $orderAmount): array
    {
        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'buyer-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $sellerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);

        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Seller',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.Str::upper(Str::random(10)),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::PaidInEscrow,
            'currency' => 'USD',
            'gross_amount' => $orderAmount,
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => $orderAmount,
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'seller_profile_id' => $seller->id,
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Item',
            'sku_snapshot' => 'SKU-'.Str::upper(Str::random(6)),
            'quantity' => 1,
            'unit_price_snapshot' => $orderAmount,
            'line_total_snapshot' => $orderAmount,
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ]);

        $buyerWalletId = (int) $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $buyer->id,
            walletType: WalletType::Buyer,
            currency: 'USD',
        ))['wallet_id'];
        $sellerWalletId = (int) $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $sellerUser->id,
            walletType: WalletType::Seller,
            currency: 'USD',
        ))['wallet_id'];

        $this->wallet->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'seed',
            referenceId: $order->id,
            idempotencyKey: 'seed-dispute-order-'.$order->id,
            entries: [
                new LedgerPostingLine(
                    walletId: $buyerWalletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: '200.0000',
                    currency: 'USD',
                    referenceType: 'seed',
                    referenceId: $order->id,
                    counterpartyWalletId: null,
                    description: 'seed_buyer',
                ),
            ],
        ));

        $create = $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $order->id,
            currency: 'USD',
            heldAmount: $orderAmount,
            idempotencyKey: 'dispute-escrow-create-'.$order->id,
        ));
        $escrowId = (int) $create['escrow_account_id'];
        $this->escrow->holdEscrow(new HoldEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'dispute-escrow-hold-'.$order->id,
        ));

        return [$order, (int) $buyer->id, $escrowId, $buyerWalletId, $sellerWalletId];
    }

    private function assertEscrowConservation(EscrowAccount $escrow, ?EscrowState $expectedTerminal = null): void
    {
        $held = $this->toScale4((string) $escrow->held_amount);
        $released = $this->toScale4((string) $escrow->released_amount);
        $refunded = $this->toScale4((string) $escrow->refunded_amount);
        $remaining = $held - $released - $refunded;

        self::assertGreaterThanOrEqual(0, $remaining, 'remaining must not be negative (conservation violation)');

        if ($expectedTerminal === EscrowState::Released || $expectedTerminal === EscrowState::Refunded) {
            self::assertSame(0, $remaining, 'terminal escrow must fully allocate held_amount');
            self::assertSame($expectedTerminal, $escrow->state);
        }
    }

    private function toScale4(string $amount): int
    {
        $normalized = trim($amount);
        if (! preg_match('/^-?\d+(\.\d{1,4})?$/', $normalized)) {
            self::fail('invalid decimal: '.$amount);
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
}

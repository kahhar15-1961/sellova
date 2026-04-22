<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\Escrow\MarkEscrowUnderDisputeCommand;
use App\Domain\Commands\Escrow\RefundEscrowCommand;
use App\Domain\Commands\Escrow\ReleaseEscrowCommand;
use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\EscrowEventType;
use App\Domain\Enums\EscrowState;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletHoldStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\EscrowReleaseConflictException;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidEscrowStateTransitionException;
use App\Domain\Value\LedgerPostingLine;
use App\Models\EscrowAccount;
use App\Models\EscrowEvent;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Services\Escrow\EscrowService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Support\Str;

final class EscrowWalletIntegrationTest extends TestCase
{
    private WalletLedgerService $wallet;
    private EscrowService $escrow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = new WalletLedgerService();
        $this->escrow = new EscrowService($this->wallet);
    }

    public function test_escrow_hold_creation_and_hold_flow_updates_escrow_wallet_ledger_holds_and_idempotency(): void
    {
        [$order, $buyerWalletId] = $this->seedSingleSellerOrderWithWallets(orderAmount: '30.0000');

        $create = $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $order->id,
            currency: 'USD',
            heldAmount: '30.0000',
            idempotencyKey: 'escrow-create-1',
        ));
        $escrowId = (int) $create['escrow_account_id'];

        $hold = $this->escrow->holdEscrow(new HoldEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'escrow-hold-1',
        ));

        self::assertSame('held', $hold['state']);

        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::Held, $escrow->state);
        self::assertSame('30.0000', (string) $escrow->held_amount);
        self::assertNotNull($escrow->held_at);

        $holdRow = WalletHold::query()
            ->where('hold_type', 'escrow')
            ->where('reference_type', 'escrow_account')
            ->where('reference_id', $escrowId)
            ->firstOrFail();
        self::assertSame(WalletHoldStatus::Consumed, $holdRow->status);
        self::assertSame($buyerWalletId, (int) $holdRow->wallet_id);

        $entry = WalletLedgerEntry::query()
            ->where('reference_type', 'escrow_account')
            ->where('reference_id', $escrowId)
            ->where('entry_type', WalletLedgerEntryType::EscrowHoldDebit)
            ->firstOrFail();
        self::assertSame($buyerWalletId, (int) $entry->wallet_id);

        self::assertSame(3, IdempotencyKey::query()->count()); // create + hold + ledger hold
        self::assertSame(2, EscrowEvent::query()->where('escrow_account_id', $escrowId)->count()); // initiated + hold
        $events = EscrowEvent::query()->where('escrow_account_id', $escrowId)->orderBy('id')->get();
        self::assertSame(EscrowEventType::Initiated, $events[0]->event_type);
        self::assertSame(EscrowEventType::Hold, $events[1]->event_type);
        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId));
    }

    public function test_release_to_seller_updates_terminal_state_wallet_balances_and_consumes_hold(): void
    {
        [$order, $buyerWalletId, $sellerWalletId] = $this->seedSingleSellerOrderWithWallets(orderAmount: '40.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '40.0000', 'release');

        $result = $this->escrow->releaseEscrow(new ReleaseEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'escrow-release-1',
        ));
        self::assertSame('released', $result['state']);
        self::assertSame('40.0000', $result['released_amount']);
        self::assertSame('0.0000', $result['refunded_amount']);

        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::Released, $escrow->state);
        self::assertNotNull($escrow->closed_at);

        $hold = WalletHold::query()
            ->where('reference_type', 'escrow_account')
            ->where('reference_id', $escrowId)
            ->firstOrFail();
        self::assertSame(WalletHoldStatus::Consumed, $hold->status);

        $buyerBalance = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        $sellerBalance = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($sellerWalletId));
        self::assertSame('60.0000', (string) $buyerBalance['available_balance']); // 100 - 40 hold debit
        self::assertSame('0.0000', (string) $buyerBalance['held_balance']);
        self::assertSame('40.0000', (string) $sellerBalance['available_balance']);

        self::assertNotNull(
            WalletLedgerEntry::query()
                ->where('wallet_id', $sellerWalletId)
                ->where('entry_type', WalletLedgerEntryType::EscrowReleaseCredit)
                ->first()
        );
        $releaseEvent = EscrowEvent::query()->where('escrow_account_id', $escrowId)->orderByDesc('id')->firstOrFail();
        self::assertSame(EscrowEventType::Release, $releaseEvent->event_type);
        $this->assertEscrowConservation($escrow, EscrowState::Released);
    }

    public function test_full_refund_updates_terminal_refunded_state_and_buyer_credit(): void
    {
        [$order, $buyerWalletId] = $this->seedSingleSellerOrderWithWallets(orderAmount: '25.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '25.0000', 'full-refund');

        $result = $this->escrow->refundEscrow(new RefundEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'escrow-refund-full-1',
            refundAmount: null,
        ));

        self::assertSame('refunded', $result['state']);
        self::assertSame('25.0000', $result['refunded_amount']);
        self::assertSame('0.0000', $result['released_amount']);

        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::Refunded, $escrow->state);

        $buyerBalance = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        self::assertSame('100.0000', (string) $buyerBalance['available_balance']); // round-trip back to original
        self::assertSame('0.0000', (string) $buyerBalance['held_balance']);
        $refundEvent = EscrowEvent::query()->where('escrow_account_id', $escrowId)->orderByDesc('id')->firstOrFail();
        self::assertSame(EscrowEventType::Refund, $refundEvent->event_type);
        $this->assertEscrowConservation($escrow, EscrowState::Refunded);
    }

    public function test_partial_refund_stays_non_terminal_and_updates_math_correctly(): void
    {
        [$order, $buyerWalletId] = $this->seedSingleSellerOrderWithWallets(orderAmount: '50.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '50.0000', 'partial-refund');

        $result = $this->escrow->refundEscrow(new RefundEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'escrow-refund-partial-1',
            refundAmount: '10.0000',
        ));

        self::assertSame('held', $result['state']);
        self::assertSame('10.0000', $result['refunded_amount']);
        self::assertSame('0.0000', $result['released_amount']);

        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::Held, $escrow->state);
        self::assertNull($escrow->closed_at);

        $buyerBalance = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        self::assertSame('60.0000', (string) $buyerBalance['available_balance']); // 100 - 50 + 10
        $this->assertEscrowConservation($escrow);
    }

    public function test_dispute_freeze_prevents_release_and_rolls_back_release_attempt(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '35.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '35.0000', 'freeze');

        $this->escrow->markUnderDispute(new MarkEscrowUnderDisputeCommand(
            escrowAccountId: $escrowId,
            disputeCaseId: 777,
        ));

        $disputeEvent = EscrowEvent::query()->where('escrow_account_id', $escrowId)->orderByDesc('id')->firstOrFail();
        self::assertSame(EscrowEventType::DisputeOpened, $disputeEvent->event_type);

        $beforeEntries = WalletLedgerEntry::query()->count();
        $beforeEvents = EscrowEvent::query()->where('escrow_account_id', $escrowId)->count();
        $beforeIdem = IdempotencyKey::query()->where('scope', 'escrow_release')->count();

        try {
            $this->escrow->releaseEscrow(new ReleaseEscrowCommand(
                escrowAccountId: $escrowId,
                idempotencyKey: 'escrow-release-blocked-1',
            ));
            self::fail('Expected release to be blocked while under dispute');
        } catch (EscrowReleaseConflictException $e) {
            self::assertSame('escrow_frozen_under_dispute', $e->reasonCode);
        }

        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::UnderDispute, $escrow->state);
        self::assertSame($beforeEntries, WalletLedgerEntry::query()->count());
        self::assertSame($beforeEvents, EscrowEvent::query()->where('escrow_account_id', $escrowId)->count());
        self::assertSame($beforeIdem, IdempotencyKey::query()->where('scope', 'escrow_release')->count());
        $this->assertEscrowConservation($escrow);
    }

    public function test_idempotent_replay_for_release_returns_existing_result_without_duplicate_rows(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '20.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '20.0000', 'release-replay');

        $r1 = $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowId, 'escrow-release-replay-1'));
        $entriesAfterR1 = WalletLedgerEntry::query()->count();
        $eventsAfterR1 = EscrowEvent::query()->where('escrow_account_id', $escrowId)->count();

        $r2 = $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowId, 'escrow-release-replay-1'));
        self::assertSame(true, $r2['idempotent_replay']);
        self::assertSame($r1['state'], $r2['state']);
        self::assertSame($entriesAfterR1, WalletLedgerEntry::query()->count());
        self::assertSame($eventsAfterR1, EscrowEvent::query()->where('escrow_account_id', $escrowId)->count());
        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId), EscrowState::Released);
    }

    public function test_rollback_on_invariant_failure_partial_then_release_is_rejected_without_new_rows(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '60.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '60.0000', 'split-block');

        $this->escrow->refundEscrow(new RefundEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'escrow-refund-partial-split-1',
            refundAmount: '5.0000',
        ));

        $beforeEntries = WalletLedgerEntry::query()->count();
        $beforeEvents = EscrowEvent::query()->where('escrow_account_id', $escrowId)->count();
        $beforeIdem = IdempotencyKey::query()->where('scope', 'escrow_release')->count();

        try {
            $this->escrow->releaseEscrow(new ReleaseEscrowCommand(
                escrowAccountId: $escrowId,
                idempotencyKey: 'escrow-release-after-partial-refund-1',
            ));
            self::fail('Expected split settlement block');
        } catch (EscrowReleaseConflictException $e) {
            self::assertSame('split_settlement_not_allowed', $e->reasonCode);
        }

        self::assertSame($beforeEntries, WalletLedgerEntry::query()->count());
        self::assertSame($beforeEvents, EscrowEvent::query()->where('escrow_account_id', $escrowId)->count());
        self::assertSame($beforeIdem, IdempotencyKey::query()->where('scope', 'escrow_release')->count());
        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId));
    }

    public function test_idempotency_conflict_release_same_key_different_escrow_payload(): void
    {
        [$orderA] = $this->seedSingleSellerOrderWithWallets(orderAmount: '11.0000');
        [$orderB] = $this->seedSingleSellerOrderWithWallets(orderAmount: '11.0000');
        $escrowA = $this->createAndHoldEscrow($orderA->id, '11.0000', 'idem-release-a');
        $escrowB = $this->createAndHoldEscrow($orderB->id, '11.0000', 'idem-release-b');

        $sharedReleaseKey = 'shared-release-'.Str::random(6);
        $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowA, $sharedReleaseKey));

        $this->expectException(IdempotencyConflictException::class);
        $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowB, $sharedReleaseKey));
    }

    public function test_idempotent_replay_escrow_create_same_order_same_key_same_payload(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '22.0000');
        $key = 'create-replay-'.Str::random(6);

        $first = $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $order->id,
            currency: 'USD',
            heldAmount: '22.0000',
            idempotencyKey: $key,
        ));
        $rowsAfterFirst = EscrowAccount::query()->count();

        $second = $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $order->id,
            currency: 'USD',
            heldAmount: '22.0000',
            idempotencyKey: $key,
        ));

        self::assertTrue($second['idempotent_replay']);
        self::assertSame((int) $first['escrow_account_id'], (int) $second['escrow_account_id']);
        self::assertSame($rowsAfterFirst, EscrowAccount::query()->count());
    }

    public function test_idempotency_conflict_escrow_create_same_key_different_payload(): void
    {
        [$orderA] = $this->seedSingleSellerOrderWithWallets(orderAmount: '10.0000');
        [$orderB] = $this->seedSingleSellerOrderWithWallets(orderAmount: '10.0000');

        $sharedKey = 'shared-create-key-'.Str::random(6);

        $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $orderA->id,
            currency: 'USD',
            heldAmount: '10.0000',
            idempotencyKey: $sharedKey,
        ));

        $this->expectException(IdempotencyConflictException::class);
        $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $orderB->id,
            currency: 'USD',
            heldAmount: '20.0000',
            idempotencyKey: $sharedKey,
        ));
    }

    public function test_idempotency_conflict_refund_same_key_different_amount(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '45.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '45.0000', 'refund-conflict');

        $this->escrow->refundEscrow(new RefundEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'refund-shared-key',
            refundAmount: '5.0000',
        ));

        $this->expectException(IdempotencyConflictException::class);
        $this->escrow->refundEscrow(new RefundEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'refund-shared-key',
            refundAmount: '10.0000',
        ));
    }

    public function test_idempotent_replay_hold_does_not_duplicate_ledger_or_holds(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '15.0000');
        $escrowId = (int) $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $order->id,
            currency: 'USD',
            heldAmount: '15.0000',
            idempotencyKey: 'escrow-create-replay-hold',
        ))['escrow_account_id'];

        $this->escrow->holdEscrow(new HoldEscrowCommand($escrowId, 'hold-replay-key'));
        $ledgerAfterFirst = WalletLedgerEntry::query()->count();
        $holdsAfterFirst = WalletHold::query()->count();

        $replay = $this->escrow->holdEscrow(new HoldEscrowCommand($escrowId, 'hold-replay-key'));
        self::assertTrue($replay['idempotent_replay']);
        self::assertSame($ledgerAfterFirst, WalletLedgerEntry::query()->count());
        self::assertSame($holdsAfterFirst, WalletHold::query()->count());
        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId));
    }

    public function test_idempotent_replay_full_refund_no_duplicate_ledger(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '18.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '18.0000', 'refund-replay');

        $this->escrow->refundEscrow(new RefundEscrowCommand($escrowId, 'full-refund-replay-key', null));
        $ledgerAfter = WalletLedgerEntry::query()->count();

        $replay = $this->escrow->refundEscrow(new RefundEscrowCommand($escrowId, 'full-refund-replay-key', null));
        self::assertTrue($replay['idempotent_replay']);
        self::assertSame($ledgerAfter, WalletLedgerEntry::query()->count());
        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId), EscrowState::Refunded);
    }

    public function test_full_refund_while_under_dispute_is_allowed_and_terminal(): void
    {
        [$order, $buyerWalletId] = $this->seedSingleSellerOrderWithWallets(orderAmount: '9.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '9.0000', 'refund-in-dispute');

        $this->escrow->markUnderDispute(new MarkEscrowUnderDisputeCommand(
            escrowAccountId: $escrowId,
            disputeCaseId: 888,
        ));

        $this->escrow->refundEscrow(new RefundEscrowCommand($escrowId, 'refund-dispute-full', null));
        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::Refunded, $escrow->state);

        $buyerBalance = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($buyerWalletId));
        self::assertSame('100.0000', (string) $buyerBalance['available_balance']);
        $this->assertEscrowConservation($escrow, EscrowState::Refunded);
    }

    public function test_terminal_state_second_release_throws_invalid_transition(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '12.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '12.0000', 'double-release');
        $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowId, 'release-once'));

        $this->expectException(InvalidEscrowStateTransitionException::class);
        $this->escrow->releaseEscrow(new ReleaseEscrowCommand($escrowId, 'release-twice'));
    }

    public function test_terminal_state_second_refund_throws_invalid_transition(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '8.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '8.0000', 'double-refund');
        $this->escrow->refundEscrow(new RefundEscrowCommand($escrowId, 'refund-once', null));

        $this->expectException(InvalidEscrowStateTransitionException::class);
        $this->escrow->refundEscrow(new RefundEscrowCommand($escrowId, 'refund-twice', null));
    }

    public function test_conservation_partial_refund_then_full_remaining_refund(): void
    {
        [$order] = $this->seedSingleSellerOrderWithWallets(orderAmount: '70.0000');
        $escrowId = $this->createAndHoldEscrow($order->id, '70.0000', 'conservation-seq');

        $this->escrow->refundEscrow(new RefundEscrowCommand($escrowId, 'partial-1', '15.0000'));
        $this->assertEscrowConservation(EscrowAccount::query()->findOrFail($escrowId));

        $this->escrow->refundEscrow(new RefundEscrowCommand($escrowId, 'full-remainder', null));
        $escrow = EscrowAccount::query()->findOrFail($escrowId);
        self::assertSame(EscrowState::Refunded, $escrow->state);
        self::assertSame('70.0000', (string) $escrow->refunded_amount);
        self::assertSame('0.0000', (string) $escrow->released_amount);
        $this->assertEscrowConservation($escrow, EscrowState::Refunded);
    }

    /**
     * Invariant: held_amount = released_amount + refunded_amount + remaining_amount (4dp integer scale).
     * Terminal released/refunded must have remaining === 0.
     */
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

    /**
     * @return array{0:Order,1:int,2:int}
     */
    private function seedSingleSellerOrderWithWallets(string $orderAmount): array
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
            'status' => OrderStatus::Paid,
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

        // Seed buyer with spendable funds.
        $this->wallet->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'seed',
            referenceId: $order->id,
            idempotencyKey: 'seed-order-'.$order->id,
            entries: [
                new LedgerPostingLine(
                    walletId: $buyerWalletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: '100.0000',
                    currency: 'USD',
                    referenceType: 'seed',
                    referenceId: $order->id,
                    counterpartyWalletId: null,
                    description: 'seed_buyer',
                ),
            ],
        ));

        return [$order, $buyerWalletId, $sellerWalletId];
    }

    private function createAndHoldEscrow(int $orderId, string $amount, string $suffix): int
    {
        $create = $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $orderId,
            currency: 'USD',
            heldAmount: $amount,
            idempotencyKey: 'escrow-create-'.$suffix,
        ));

        $escrowId = (int) $create['escrow_account_id'];
        $this->escrow->holdEscrow(new HoldEscrowCommand(
            escrowAccountId: $escrowId,
            idempotencyKey: 'escrow-hold-'.$suffix,
        ));

        return $escrowId;
    }
}


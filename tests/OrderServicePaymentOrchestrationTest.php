<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Commands\Order\MarkOrderPaidCommand;
use App\Domain\Commands\Order\MarkOrderPendingPaymentCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Enums\EscrowState;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Models\EscrowAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletLedgerBatch;
use App\Services\Order\OrderService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Support\Str;

final class OrderServicePaymentOrchestrationTest extends TestCase
{
    private OrderService $orders;
    private WalletLedgerService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = new WalletLedgerService();
        $this->orders = new OrderService($this->wallet);
    }

    public function test_mark_pending_payment_transitions_draft_to_pending_payment(): void
    {
        [$order] = $this->seedSingleSellerOrder(OrderStatus::Draft, '25.0000');
        $result = $this->orders->markPendingPayment(new MarkOrderPendingPaymentCommand(
            orderId: $order->id,
            actorUserId: (int) $order->buyer_user_id,
        ));
        self::assertSame('pending_payment', $result['status']);
        $order->refresh();
        self::assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_mark_paid_posts_capture_funding_escrow_hold_and_sets_escrow_funded(): void
    {
        [$order] = $this->seedSingleSellerOrder(OrderStatus::PendingPayment, '42.0000');
        [, $txn] = $this->seedCapturedPayment($order, '42.0000');

        $result = $this->orders->markPaid(new MarkOrderPaidCommand(
            orderId: $order->id,
            paymentTransactionId: $txn->id,
            actorUserId: (int) $order->buyer_user_id,
        ));
        self::assertSame('escrow_funded', $result['status']);
        self::assertSame('held', $result['escrow_state']);

        $order->refresh();
        self::assertSame(OrderStatus::EscrowFunded, $order->status);
        self::assertNotNull($order->placed_at);

        $escrow = EscrowAccount::query()->where('order_id', $order->id)->firstOrFail();
        self::assertSame(EscrowState::Held, $escrow->state);
        self::assertSame('42.0000', (string) $escrow->held_amount);

        self::assertTrue(
            WalletLedgerBatch::query()
                ->where('reference_type', 'payment_transaction')
                ->where('reference_id', $txn->id)
                ->where('event_name', LedgerPostingEventName::PaymentCapture)
                ->exists()
        );
    }

    public function test_mark_paid_idempotent_replay(): void
    {
        [$order] = $this->seedSingleSellerOrder(OrderStatus::PendingPayment, '15.0000');
        [, $txn] = $this->seedCapturedPayment($order, '15.0000');

        $buyerId = (int) $order->buyer_user_id;
        $first = $this->orders->markPaid(new MarkOrderPaidCommand($order->id, $txn->id, null, $buyerId));
        $second = $this->orders->markPaid(new MarkOrderPaidCommand($order->id, $txn->id, null, $buyerId));

        self::assertFalse($first['idempotent_replay']);
        self::assertTrue($second['idempotent_replay']);
        self::assertSame((int) $first['escrow_account_id'], (int) $second['escrow_account_id']);
    }

    public function test_mark_paid_rejects_second_payment_after_paid_in_escrow(): void
    {
        [$order] = $this->seedSingleSellerOrder(OrderStatus::PendingPayment, '18.0000');
        [, $txn1] = $this->seedCapturedPayment($order, '18.0000');
        $buyerId = (int) $order->buyer_user_id;
        $this->orders->markPaid(new MarkOrderPaidCommand($order->id, $txn1->id, null, $buyerId));

        [, $txn2] = $this->seedCapturedPayment($order, '18.0000');

        $this->expectException(OrderValidationFailedException::class);
        try {
            $this->orders->markPaid(new MarkOrderPaidCommand($order->id, $txn2->id, null, $buyerId));
        } catch (OrderValidationFailedException $e) {
            self::assertSame('order_already_paid_in_escrow', $e->reasonCode);
            throw $e;
        }
    }

    public function test_mark_paid_rejects_multi_seller(): void
    {
        [$order] = $this->seedSingleSellerOrder(OrderStatus::PendingPayment, '30.0000');
        $sellerBUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-b-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $sellerB = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerBUser->id,
            'display_name' => 'Seller B',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $sellerBUser->id,
            walletType: WalletType::Seller,
            currency: 'USD',
        ));

        OrderItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'seller_profile_id' => $sellerB->id,
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Second line',
            'sku_snapshot' => 'SKU-B',
            'quantity' => 1,
            'unit_price_snapshot' => '10.0000',
            'line_total_snapshot' => '10.0000',
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ]);

        [, $txn] = $this->seedCapturedPayment($order, '30.0000');

        $this->expectException(OrderValidationFailedException::class);
        try {
            $this->orders->markPaid(new MarkOrderPaidCommand($order->id, $txn->id, null, (int) $order->buyer_user_id));
        } catch (OrderValidationFailedException $e) {
            self::assertSame('multi_seller_escrow_not_supported', $e->reasonCode);
            throw $e;
        }
    }

    public function test_mark_paid_from_draft_throws_invalid_transition(): void
    {
        [$order] = $this->seedSingleSellerOrder(OrderStatus::Draft, '20.0000');
        [, $txn] = $this->seedCapturedPayment($order, '20.0000');

        $this->expectException(InvalidOrderStateTransitionException::class);
        $this->orders->markPaid(new MarkOrderPaidCommand($order->id, $txn->id, null, (int) $order->buyer_user_id));
    }

    public function test_mark_pending_payment_denied_for_seller_actor(): void
    {
        [$order, , $sellerProfile] = $this->seedSingleSellerOrder(OrderStatus::Draft, '11.0000');
        $sellerUserId = (int) $sellerProfile->user_id;

        $this->expectException(DomainAuthorizationDeniedException::class);
        $this->orders->markPendingPayment(new MarkOrderPendingPaymentCommand(
            orderId: $order->id,
            actorUserId: $sellerUserId,
        ));
    }

    public function test_mark_paid_denied_for_seller_actor(): void
    {
        [$order, , $sellerProfile] = $this->seedSingleSellerOrder(OrderStatus::PendingPayment, '12.0000');
        [, $txn] = $this->seedCapturedPayment($order, '12.0000');
        $sellerUserId = (int) $sellerProfile->user_id;

        $this->expectException(DomainAuthorizationDeniedException::class);
        $this->orders->markPaid(new MarkOrderPaidCommand(
            orderId: $order->id,
            paymentTransactionId: $txn->id,
            actorUserId: $sellerUserId,
        ));
    }

    public function test_resolve_buyer_and_seller_wallet_ids(): void
    {
        [$order] = $this->seedSingleSellerOrder(OrderStatus::Draft, '5.0000');
        $buyerId = $this->orders->resolveBuyerWalletId($order);
        $sellerId = $this->orders->resolveSellerWalletId($order);
        self::assertGreaterThan(0, $buyerId);
        self::assertGreaterThan(0, $sellerId);
        self::assertNotSame($buyerId, $sellerId);
    }

    /**
     * @return array{0: Order, 1: int, 2: SellerProfile}
     */
    private function seedSingleSellerOrder(OrderStatus $initialStatus, string $netAmount): array
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
            'status' => $initialStatus,
            'currency' => 'USD',
            'gross_amount' => $netAmount,
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => $netAmount,
            'placed_at' => null,
        ]);

        OrderItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'seller_profile_id' => $seller->id,
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Item',
            'sku_snapshot' => 'SKU-'.Str::upper(Str::random(6)),
            'quantity' => 1,
            'unit_price_snapshot' => $netAmount,
            'line_total_snapshot' => $netAmount,
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ]);

        $buyerWalletId = (int) $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $buyer->id,
            walletType: WalletType::Buyer,
            currency: 'USD',
        ))['wallet_id'];

        $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $sellerUser->id,
            walletType: WalletType::Seller,
            currency: 'USD',
        ));

        return [$order, $buyerWalletId, $seller];
    }

    /**
     * @return array{0: PaymentIntent, 1: PaymentTransaction}
     */
    private function seedCapturedPayment(Order $order, string $amount): array
    {
        $intent = PaymentIntent::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'provider' => 'test',
            'provider_intent_ref' => 'pi_'.Str::random(12),
            'status' => 'captured',
            'amount' => $amount,
            'currency' => (string) $order->currency,
            'expires_at' => null,
        ]);

        $txn = PaymentTransaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'payment_intent_id' => $intent->id,
            'order_id' => $order->id,
            'provider_txn_ref' => 'txn_'.Str::random(12),
            'txn_type' => 'capture',
            'status' => 'success',
            'amount' => $amount,
            'raw_payload_json' => [],
            'processed_at' => now(),
        ]);

        return [$intent, $txn];
    }
}

<?php

namespace App\Services\Order;

use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\Order\AdvanceOrderFulfillmentCommand;
use App\Domain\Commands\Order\CompleteOrderCommand;
use App\Domain\Commands\Order\CreateOrderCommand;
use App\Domain\Commands\Order\MarkOrderPaidCommand;
use App\Domain\Commands\Order\MarkOrderPendingPaymentCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Models\EscrowAccount;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderStateTransition;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\SellerProfile;
use App\Services\Escrow\EscrowService;
use App\Services\Support\FinancialCritical;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    use FinancialCritical;

    private readonly WalletLedgerService $walletLedgerService;

    private readonly EscrowService $escrowService;

    public function __construct(
        ?WalletLedgerService $walletLedgerService = null,
        ?EscrowService $escrowService = null,
    ) {
        $this->walletLedgerService = $walletLedgerService ?? new WalletLedgerService();
        $this->escrowService = $escrowService ?? new EscrowService($this->walletLedgerService);
    }

    public function createOrder(CreateOrderCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * Draft → pending_payment. Does not touch payments or escrow.
     */
    public function markPendingPayment(MarkOrderPendingPaymentCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            if ($order->status !== OrderStatus::Draft) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::PendingPayment->value,
                );
            }

            $from = $order->status;
            $order->status = OrderStatus::PendingPayment;
            $order->save();

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::PendingPayment,
                reasonCode: 'checkout_pending_payment',
                actorUserId: $command->actorUserId,
                correlationId: $command->correlationId ?? (string) Str::uuid(),
            );

            return [
                'order_id' => $order->id,
                'status' => $order->status->value,
            ];
        });
    }

    /**
     * Pending payment → paid, after a successful capture transaction, by creating escrow and placing the hold.
     *
     * Multi-seller orders are rejected in {@see self::assertSingleSellerOrderForEscrow} (documented in `docs/ORDER_PAYMENT_ORCHESTRATION.md`).
     */
    public function markPaid(MarkOrderPaidCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $idemKey = $this->orderMarkPaidIdempotencyKey($command->orderId, $command->paymentTransactionId);
            $requestHash = $this->hashPayload([
                'order_id' => $command->orderId,
                'payment_transaction_id' => $command->paymentTransactionId,
            ]);
            $idem = $this->claimOrderMarkPaidIdempotency($idemKey, $requestHash);
            if ($idem['replay']) {
                return $this->buildMarkPaidReplayPayload($command->orderId, $command->paymentTransactionId);
            }

            $txn = PaymentTransaction::query()->whereKey($command->paymentTransactionId)->lockForUpdate()->first();
            if ($txn === null) {
                throw new OrderValidationFailedException($command->orderId, 'payment_transaction_not_found', [
                    'payment_transaction_id' => $command->paymentTransactionId,
                ]);
            }

            $intent = PaymentIntent::query()->whereKey($txn->payment_intent_id)->lockForUpdate()->first();
            if ($intent === null) {
                throw new OrderValidationFailedException($command->orderId, 'payment_intent_not_found', [
                    'payment_intent_id' => $txn->payment_intent_id,
                ]);
            }

            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            $this->assertPaymentCaptureAppliesToOrder($order, $intent, $txn);

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::Paid->value,
                );
            }

            $order->load(['orderItems']);
            $this->assertSingleSellerOrderForEscrow($order);
            $this->ensureBuyerAndSellerWalletsForOrder($order);

            $escrowCreateKey = $this->escrowCreateIdempotencyKey($command->orderId, $command->paymentTransactionId);
            $escrowHoldKey = $this->escrowHoldIdempotencyKey($command->orderId, $command->paymentTransactionId);

            try {
                $create = $this->escrowService->createEscrowForOrder(new CreateEscrowForOrderCommand(
                    orderId: $order->id,
                    currency: (string) $order->currency,
                    heldAmount: (string) $order->net_amount,
                    idempotencyKey: $escrowCreateKey,
                ));

                $this->escrowService->holdEscrow(new HoldEscrowCommand(
                    escrowAccountId: (int) $create['escrow_account_id'],
                    idempotencyKey: $escrowHoldKey,
                ));
            } catch (InsufficientWalletBalanceException $e) {
                throw new OrderValidationFailedException(
                    orderId: $order->id,
                    reasonCode: 'buyer_wallet_insufficient_for_escrow_hold',
                    violations: [
                        'wallet_id' => $e->walletId,
                        'currency' => $e->currency,
                        'requested_amount' => $e->requestedAmount,
                        'available_amount' => $e->availableAmount,
                    ],
                    previous: $e,
                );
            }

            $from = $order->status;
            $order->status = OrderStatus::Paid;
            if ($order->placed_at === null) {
                $order->placed_at = now();
            }
            $order->save();

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::Paid,
                reasonCode: 'payment_capture_settled',
                actorUserId: $command->actorUserId,
                correlationId: $command->correlationId ?? (string) Str::uuid(),
            );

            $escrow = EscrowAccount::query()->where('order_id', $order->id)->firstOrFail();

            $this->markIdempotencySucceeded($idem['idempotency'], [
                'order_id' => $order->id,
                'payment_transaction_id' => $command->paymentTransactionId,
                'escrow_account_id' => $escrow->id,
            ]);

            return [
                'order_id' => $order->id,
                'status' => $order->status->value,
                'escrow_account_id' => $escrow->id,
                'escrow_state' => $escrow->state->value,
                'idempotent_replay' => false,
            ];
        });
    }


    public function advanceFulfillment(AdvanceOrderFulfillmentCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function completeOrder(CompleteOrderCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * Single-seller orchestration guard (canonical multi-seller rejection for payment → escrow).
     *
     * @see docs/ORDER_PAYMENT_ORCHESTRATION.md
     */
    private function assertSingleSellerOrderForEscrow(Order $order): void
    {
        if ($order->orderItems->isEmpty()) {
            throw new OrderValidationFailedException($order->id, 'order_has_no_line_items', []);
        }

        $sellerIds = $order->orderItems->pluck('seller_profile_id')->unique()->values();
        if ($sellerIds->count() !== 1) {
            throw new OrderValidationFailedException($order->id, 'multi_seller_escrow_not_supported', [
                'seller_profile_ids' => $sellerIds->all(),
            ]);
        }
    }

    private function assertPaymentCaptureAppliesToOrder(Order $order, PaymentIntent $intent, PaymentTransaction $txn): void
    {
        if ((int) $txn->order_id !== (int) $order->id) {
            throw new OrderValidationFailedException($order->id, 'payment_transaction_order_mismatch', [
                'order_id' => $order->id,
                'payment_transaction_id' => $txn->id,
            ]);
        }
        if ((int) $intent->order_id !== (int) $order->id) {
            throw new OrderValidationFailedException($order->id, 'payment_intent_order_mismatch', [
                'order_id' => $order->id,
                'payment_intent_id' => $intent->id,
            ]);
        }
        if ((int) $txn->payment_intent_id !== (int) $intent->id) {
            throw new OrderValidationFailedException($order->id, 'payment_transaction_intent_mismatch', [
                'payment_intent_id' => $intent->id,
                'payment_transaction_id' => $txn->id,
            ]);
        }
        if ($intent->status !== 'captured') {
            throw new OrderValidationFailedException($order->id, 'payment_intent_not_captured', [
                'payment_intent_id' => $intent->id,
                'status' => $intent->status,
            ]);
        }
        if ($txn->status !== 'success') {
            throw new OrderValidationFailedException($order->id, 'payment_transaction_not_success', [
                'payment_transaction_id' => $txn->id,
                'status' => $txn->status,
            ]);
        }
        if ($txn->txn_type !== 'capture') {
            throw new OrderValidationFailedException($order->id, 'payment_transaction_not_capture', [
                'payment_transaction_id' => $txn->id,
                'txn_type' => $txn->txn_type,
            ]);
        }
        if ((string) $intent->currency !== (string) $order->currency) {
            throw new OrderValidationFailedException($order->id, 'payment_currency_mismatch', [
                'order_currency' => (string) $order->currency,
                'intent_currency' => (string) $intent->currency,
            ]);
        }
        if ((string) $intent->amount !== (string) $order->net_amount) {
            throw new OrderValidationFailedException($order->id, 'payment_intent_amount_mismatch', [
                'order_net_amount' => (string) $order->net_amount,
                'intent_amount' => (string) $intent->amount,
            ]);
        }
        if ((string) $txn->amount !== (string) $order->net_amount) {
            throw new OrderValidationFailedException($order->id, 'payment_transaction_amount_mismatch', [
                'order_net_amount' => (string) $order->net_amount,
                'txn_amount' => (string) $txn->amount,
            ]);
        }
    }

    private function ensureBuyerAndSellerWalletsForOrder(Order $order): void
    {
        $currency = (string) $order->currency;
        $buyerUserId = (int) $order->buyer_user_id;

        $this->walletLedgerService->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $buyerUserId,
            walletType: WalletType::Buyer,
            currency: $currency,
        ));

        $sellerProfileId = (int) $order->orderItems->first()->seller_profile_id;
        $seller = SellerProfile::query()->whereKey($sellerProfileId)->lockForUpdate()->first();
        if ($seller === null) {
            throw new OrderValidationFailedException($order->id, 'seller_profile_not_found', [
                'seller_profile_id' => $sellerProfileId,
            ]);
        }

        $this->walletLedgerService->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: (int) $seller->user_id,
            walletType: WalletType::Seller,
            currency: $currency,
        ));
    }

    private function recordOrderStateTransition(
        Order $order,
        OrderStatus $from,
        OrderStatus $to,
        string $reasonCode,
        ?int $actorUserId,
        string $correlationId,
    ): void {
        OrderStateTransition::query()->create([
            'order_id' => $order->id,
            'from_state' => $from->value,
            'to_state' => $to->value,
            'reason_code' => $reasonCode,
            'actor_user_id' => $actorUserId,
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);
    }

    private function orderMarkPaidIdempotencyKey(int $orderId, int $paymentTransactionId): string
    {
        return 'order:'.$orderId.':mark_paid:txn:'.$paymentTransactionId;
    }

    private function escrowCreateIdempotencyKey(int $orderId, int $paymentTransactionId): string
    {
        return 'order:'.$orderId.':mark_paid:txn:'.$paymentTransactionId.':escrow_create';
    }

    private function escrowHoldIdempotencyKey(int $orderId, int $paymentTransactionId): string
    {
        return 'order:'.$orderId.':mark_paid:txn:'.$paymentTransactionId.':escrow_hold';
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{idempotency: IdempotencyKey, replay: bool}
     */
    private function claimOrderMarkPaidIdempotency(string $key, string $requestHash): array
    {
        $scope = 'order_mark_paid';
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

    private function markIdempotencySucceeded(IdempotencyKey $key, array $response): void
    {
        $key->status = IdempotencyKeyStatus::Succeeded;
        $key->response_hash = hash('sha256', json_encode($response, JSON_THROW_ON_ERROR));
        $key->save();
    }

    /**
     * @return array{order_id: int, status: string, escrow_account_id: int, escrow_state: string, idempotent_replay: true}
     */
    private function buildMarkPaidReplayPayload(int $orderId, int $paymentTransactionId): array
    {
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        $escrow = EscrowAccount::query()->where('order_id', $orderId)->firstOrFail();

        return [
            'order_id' => $order->id,
            'status' => $order->status->value,
            'escrow_account_id' => $escrow->id,
            'escrow_state' => $escrow->state->value,
            'idempotent_replay' => true,
        ];
    }
}

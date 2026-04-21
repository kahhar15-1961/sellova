<?php

namespace App\Services\Order;

use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\Order\AdvanceOrderFulfillmentCommand;
use App\Domain\Commands\Order\ApplyOrderStatusAfterDisputeResolutionCommand;
use App\Domain\Commands\Order\CompleteOrderCommand;
use App\Domain\Commands\Order\CreateOrderCommand;
use App\Domain\Commands\Order\MarkOrderDisputedCommand;
use App\Domain\Commands\Order\MarkOrderPaidCommand;
use App\Domain\Commands\Order\MarkOrderPendingPaymentCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
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
use App\Models\Wallet;
use App\Domain\Value\LedgerPostingLine;
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
     * Resolves the buyer wallet for the order currency (creates wallet if missing).
     */
    public function resolveBuyerWalletId(Order $order): int
    {
        $currency = (string) $order->currency;
        $this->walletLedgerService->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: (int) $order->buyer_user_id,
            walletType: WalletType::Buyer,
            currency: $currency,
        ));

        $wallet = Wallet::query()
            ->where('user_id', $order->buyer_user_id)
            ->where('wallet_type', WalletType::Buyer->value)
            ->where('currency', $currency)
            ->firstOrFail();

        return (int) $wallet->id;
    }

    /**
     * Resolves the single seller wallet for the order (creates wallet if missing).
     *
     * @throws OrderValidationFailedException when the order is not single-seller
     */
    public function resolveSellerWalletId(Order $order): int
    {
        $order->loadMissing('orderItems');
        $this->assertSingleSellerOrderForEscrow($order);

        $currency = (string) $order->currency;
        $sellerProfileId = (int) $order->orderItems->first()->seller_profile_id;
        $seller = SellerProfile::query()->whereKey($sellerProfileId)->first();
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

        $wallet = Wallet::query()
            ->where('user_id', $seller->user_id)
            ->where('wallet_type', WalletType::Seller->value)
            ->where('currency', $currency)
            ->firstOrFail();

        return (int) $wallet->id;
    }

    /**
     * Pending payment → paid_in_escrow: posts capture funding to the buyer wallet via {@see WalletLedgerService},
     * then {@see EscrowService} creates escrow and places the hold — single transaction, no partial commits.
     *
     * Multi-seller orders are rejected in {@see self::assertSingleSellerOrderForEscrow} (see `docs/ORDER_PAYMENT_ORCHESTRATION.md`).
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

            if ($order->status === OrderStatus::PaidInEscrow || $order->status === OrderStatus::Paid) {
                throw new OrderValidationFailedException($order->id, 'order_already_paid_in_escrow', [
                    'current_status' => $order->status->value,
                    'payment_transaction_id' => $command->paymentTransactionId,
                ]);
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::PaidInEscrow->value,
                );
            }

            $order->load(['orderItems']);
            $this->assertSingleSellerOrderForEscrow($order);

            $buyerWalletId = $this->resolveBuyerWalletId($order);
            $this->resolveSellerWalletId($order);

            $this->postFundingForOrderFromCapturedPayment($order, $txn, $buyerWalletId);

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
            $order->status = OrderStatus::PaidInEscrow;
            if ($order->placed_at === null) {
                $order->placed_at = now();
            }
            $order->save();

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::PaidInEscrow,
                reasonCode: 'payment_capture_funded_and_escrow_held',
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
     * paid_in_escrow → disputed (opens dispute workflow; caller should hold row locks in the same transaction).
     */
    public function markOrderDisputed(MarkOrderDisputedCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            if ($order->status !== OrderStatus::PaidInEscrow) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::Disputed->value,
                );
            }

            $from = $order->status;
            $order->status = OrderStatus::Disputed;
            $order->save();

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::Disputed,
                reasonCode: 'dispute_opened',
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
     * disputed → refunded | paid_in_escrow after dispute resolution (idempotent if order already at target).
     */
    public function applyOrderStatusAfterDisputeResolution(ApplyOrderStatusAfterDisputeResolutionCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            if ($order->status === $command->targetStatus) {
                return [
                    'order_id' => $order->id,
                    'status' => $order->status->value,
                    'idempotent_replay' => true,
                ];
            }

            if ($order->status !== OrderStatus::Disputed) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: $command->targetStatus->value,
                );
            }

            $allowed = [OrderStatus::Refunded, OrderStatus::PaidInEscrow];
            if (! in_array($command->targetStatus, $allowed, true)) {
                throw new OrderValidationFailedException($order->id, 'invalid_post_dispute_order_status', [
                    'target_status' => $command->targetStatus->value,
                ]);
            }

            $from = $order->status;
            $order->status = $command->targetStatus;
            $order->save();

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: $command->targetStatus,
                reasonCode: $command->reasonCode,
                actorUserId: $command->actorUserId,
                correlationId: $command->correlationId ?? (string) Str::uuid(),
            );

            return [
                'order_id' => $order->id,
                'status' => $order->status->value,
                'idempotent_replay' => false,
            ];
        });
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

    /**
     * Credits the buyer wallet for the PSP capture amount using {@see WalletLedgerService::postLedgerBatch}.
     * Idempotent per order + payment transaction.
     */
    private function postFundingForOrderFromCapturedPayment(Order $order, PaymentTransaction $txn, int $buyerWalletId): void
    {
        $amount = (string) $txn->amount;
        $currency = (string) $order->currency;

        $this->walletLedgerService->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::PaymentCapture,
            referenceType: 'payment_transaction',
            referenceId: $txn->id,
            idempotencyKey: $this->paymentCaptureFundingIdempotencyKey($order->id, $txn->id),
            entries: [
                new LedgerPostingLine(
                    walletId: $buyerWalletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: $amount,
                    currency: $currency,
                    referenceType: 'payment_transaction',
                    referenceId: $txn->id,
                    counterpartyWalletId: null,
                    description: 'order_payment_capture',
                ),
            ],
        ));
    }

    private function paymentCaptureFundingIdempotencyKey(int $orderId, int $paymentTransactionId): string
    {
        return 'order:'.$orderId.':payment_capture_funding:txn:'.$paymentTransactionId;
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
        if ($order->status !== OrderStatus::PaidInEscrow && $order->status !== OrderStatus::Paid) {
            throw new OrderValidationFailedException($orderId, 'order_payment_orchestration_replay_state_mismatch', [
                'current_status' => $order->status->value,
                'payment_transaction_id' => $paymentTransactionId,
            ]);
        }

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

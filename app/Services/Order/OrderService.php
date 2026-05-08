<?php

namespace App\Services\Order;

use App\Auth\Ability;
use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\Escrow\RefundEscrowCommand;
use App\Domain\Commands\Escrow\ReleaseEscrowCommand;
use App\Domain\Commands\Order\AdvanceOrderFulfillmentCommand;
use App\Domain\Commands\Order\AddOrderShippingDetailsCommand;
use App\Domain\Commands\Order\CancelOrderCommand;
use App\Domain\Commands\Order\ApplyOrderStatusAfterDisputeResolutionCommand;
use App\Domain\Commands\Order\CompleteOrderCommand;
use App\Domain\Commands\Order\CreateOrderCommand;
use App\Domain\Queries\Orders\OrderListQuery;
use App\Domain\Commands\Order\MarkOrderDisputedCommand;
use App\Domain\Commands\Order\MarkOrderPaidCommand;
use App\Domain\Commands\Order\MarkOrderPendingPaymentCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\ProductType;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Domain\Exceptions\InvalidOrderStateTransitionException;
use App\Domain\Exceptions\PromotionValidationFailedException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Auth\OrderParticipant;
use App\Models\EscrowAccount;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStateTransition;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Http\Resources\OrderResource;
use App\Policies\OrderPolicy;
use App\Domain\Value\LedgerPostingLine;
use App\Services\Escrow\EscrowService;
use App\Services\Escrow\EscrowReleaseService;
use App\Services\PaymentGateway\PaymentGatewayService;
use App\Services\Promotion\PromotionService;
use App\Services\Support\FinancialCritical;
use App\Services\TimeoutAutomation\OrderTimeoutSnapshotService;
use App\Services\TimeoutAutomation\TimeoutAutomationService;
use App\Services\TimeoutAutomation\TimeoutNotificationService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    use FinancialCritical;

    private readonly WalletLedgerService $walletLedgerService;

    private readonly EscrowService $escrowService;

    private readonly PromotionService $promotionService;

    private readonly PaymentGatewayService $paymentGatewayService;
    private readonly OrderStateMachine $stateMachine;
    private readonly FulfillmentService $fulfillmentService;
    private readonly EscrowReleaseService $escrowReleaseService;
    private readonly OrderTimeoutSnapshotService $timeoutSnapshots;

    public function __construct(
        ?WalletLedgerService $walletLedgerService = null,
        ?EscrowService $escrowService = null,
        ?PaymentGatewayService $paymentGatewayService = null,
    ) {
        $this->walletLedgerService = $walletLedgerService ?? new WalletLedgerService();
        $this->escrowService = $escrowService ?? new EscrowService($this->walletLedgerService);
        $this->promotionService = new PromotionService();
        $this->paymentGatewayService = $paymentGatewayService ?? new PaymentGatewayService();
        $this->stateMachine = new OrderStateMachine();
        $this->fulfillmentService = new FulfillmentService($this->stateMachine);
        $this->escrowReleaseService = new EscrowReleaseService($this->escrowService);
        $this->timeoutSnapshots = new OrderTimeoutSnapshotService();
    }

    public function createOrder(CreateOrderCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $lines = $command->cartSnapshot->lines;
            if ($lines === []) {
                throw new OrderValidationFailedException($command->buyerUserId, 'order_has_no_line_items', []);
            }

            $requestHash = $this->hashPayload([
                'buyer_user_id' => $command->buyerUserId,
                'shipping_method' => $command->shippingMethod,
                'promo_code' => $command->promoCode,
                'lines' => array_map(static fn ($line) => [
                    'product_id' => $line->productId,
                    'product_variant_id' => $line->productVariantId,
                    'seller_profile_id' => $line->sellerProfileId,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'currency' => $line->currency,
                ], $lines),
            ]);
            $idem = $this->claimOrderCreateIdempotency($command->idempotencyKey, $requestHash);
            $orderNumber = $this->generateOrderNumber($command->idempotencyKey);

            if ($idem['replay']) {
                $order = Order::query()
                    ->where('order_number', $orderNumber)
                    ->with(['orderItems', 'escrowAccount'])
                    ->firstOrFail();

                return array_merge(OrderResource::detail($order), [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'idempotent_replay' => true,
                ]);
            }

            $gross = 0.0;
            $currency = null;
            $sellerProfileIds = [];
            $sellerUserIds = [];
            $productIds = [];
            $productTypes = [];
            $itemRows = [];
            $needsShipping = false;

            foreach ($lines as $line) {
                if ($line->quantity < 1) {
                    throw new OrderValidationFailedException($command->buyerUserId, 'invalid_quantity', [
                        'product_id' => $line->productId,
                    ]);
                }

                $product = Product::query()
                    ->with(['seller_profile'])
                    ->whereKey($line->productId)
                    ->first();
                if ($product === null) {
                    throw new OrderValidationFailedException($command->buyerUserId, 'product_not_found', [
                        'product_id' => $line->productId,
                    ]);
                }

                $variant = null;
                if ($line->productVariantId !== null) {
                    $variant = ProductVariant::query()
                        ->whereKey($line->productVariantId)
                        ->where('product_id', $product->id)
                        ->first();
                    if ($variant === null) {
                        throw new OrderValidationFailedException($command->buyerUserId, 'product_variant_not_found', [
                            'product_variant_id' => $line->productVariantId,
                            'product_id' => $line->productId,
                        ]);
                    }
                }

                $sellerProfileId = (int) $product->seller_profile_id;
                if ($line->sellerProfileId > 0 && $line->sellerProfileId !== $sellerProfileId) {
                    throw new OrderValidationFailedException($command->buyerUserId, 'seller_profile_mismatch', [
                        'product_id' => $line->productId,
                        'seller_profile_id' => $line->sellerProfileId,
                    ]);
                }
                if ((int) ($product->seller_profile?->user_id ?? 0) === $command->buyerUserId) {
                    throw new OrderValidationFailedException($command->buyerUserId, 'self_purchase_not_allowed', [
                        'product_id' => $line->productId,
                        'seller_profile_id' => $sellerProfileId,
                    ]);
                }

                $lineCurrency = strtoupper(trim((string) $line->currency));
                if ($lineCurrency === '') {
                    throw new OrderValidationFailedException($command->buyerUserId, 'order_currency_missing', [
                        'product_id' => $line->productId,
                    ]);
                }
                if ($currency === null) {
                    $currency = $lineCurrency;
                } elseif ($currency !== $lineCurrency) {
                    throw new OrderValidationFailedException($command->buyerUserId, 'mixed_currency_checkout_not_supported', [
                        'expected_currency' => $currency,
                        'received_currency' => $lineCurrency,
                    ]);
                }

                $sellerProfileIds[] = $sellerProfileId;
                $sellerUserIds[] = (int) ($product->seller_profile?->user_id ?? 0);
                $productIds[] = (int) $product->id;
                $productType = ProductType::normalize((string) $product->product_type);
                $productTypes[] = $productType->value;
                if ($productType === ProductType::Physical) {
                    $needsShipping = true;
                }

                $unitPrice = round((float) $line->unitPrice, 4);
                $lineTotal = round($unitPrice * $line->quantity, 4);
                $gross += $lineTotal;

                $itemRows[] = [
                    'uuid' => (string) Str::uuid(),
                    'seller_profile_id' => $sellerProfileId,
                    'product_id' => (int) $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_type_snapshot' => $productType->value,
                    'title_snapshot' => (string) ($variant?->title ?: $product->title ?: 'Product #'.$product->id),
                    'sku_snapshot' => $variant?->sku,
                    'quantity' => $line->quantity,
                    'unit_price_snapshot' => number_format($unitPrice, 4, '.', ''),
                    'line_total_snapshot' => number_format($lineTotal, 4, '.', ''),
                    'commission_rule_snapshot_json' => [],
                    'delivery_state' => 'not_started',
                ];
            }

            $sellerProfileIds = array_values(array_unique($sellerProfileIds));
            if (count($sellerProfileIds) !== 1) {
                throw new OrderValidationFailedException($command->buyerUserId, 'multi_seller_escrow_not_supported', [
                    'seller_profile_ids' => $sellerProfileIds,
                ]);
            }
            $sellerUserIds = array_values(array_unique(array_filter($sellerUserIds)));
            if (count($sellerUserIds) !== 1) {
                throw new OrderValidationFailedException($command->buyerUserId, 'seller_owner_not_resolved', [
                    'seller_user_ids' => $sellerUserIds,
                ]);
            }
            $productTypes = array_values(array_unique($productTypes));
            if (count($productTypes) !== 1) {
                throw new OrderValidationFailedException($command->buyerUserId, 'mixed_product_type_checkout_not_supported', [
                    'product_types' => $productTypes,
                ]);
            }

            $currency = $currency ?? 'USD';
            $grossAmount = number_format($gross, 4, '.', '');
            $shippingMethod = $this->normalizeShippingMethod($command->shippingMethod);
            $shippingFee = $command->shippingMethodProvided
                ? $this->promotionService->shippingFeeForMethod($shippingMethod, $needsShipping)
                : 0.0;
            $promoCode = $command->promoCode !== null && trim($command->promoCode) !== ''
                ? strtoupper(trim($command->promoCode))
                : null;
            $discountAmount = 0.0;
            if ($promoCode !== null) {
                $promo = $this->promotionService->consumePromoCode(
                    rawCode: $promoCode,
                    subtotal: $gross,
                    shippingFee: $shippingFee,
                    currency: $currency,
                    shippingMethod: $shippingMethod,
                );
                $discountAmount = (float) $promo['estimated_discount_amount'];
            }
            $netAmount = max(0.0, $gross + $shippingFee - $discountAmount);
            $netAmountFormatted = number_format($netAmount, 4, '.', '');

            $orderAttributes = [
                'uuid' => (string) Str::uuid(),
                'order_number' => $orderNumber,
                'buyer_user_id' => $command->buyerUserId,
                'seller_user_id' => $sellerUserIds[0],
                'primary_product_id' => $productIds[0] ?? null,
                'product_type' => $productTypes[0],
                'status' => OrderStatus::PendingPayment,
                'fulfillment_state' => 'not_started',
                'currency' => $currency,
                'gross_amount' => $grossAmount,
                'discount_amount' => number_format($discountAmount, 4, '.', ''),
                'fee_amount' => number_format($shippingFee, 4, '.', ''),
                'net_amount' => $netAmountFormatted,
                'placed_at' => null,
            ];
            if (Schema::hasColumn('orders', 'promo_code')) {
                $orderAttributes['promo_code'] = $promoCode;
            }
            if ($needsShipping && Schema::hasColumn('orders', 'shipping_method')) {
                $orderAttributes['shipping_method'] = $shippingMethod;
            }
            if ($needsShipping && Schema::hasColumn('orders', 'shipping_address_id')) {
                $orderAttributes['shipping_address_id'] = $command->shippingAddressId;
            }
            if ($needsShipping && Schema::hasColumn('orders', 'shipping_recipient_name')) {
                $orderAttributes['shipping_recipient_name'] = $command->shippingRecipientName;
            }
            if ($needsShipping && Schema::hasColumn('orders', 'shipping_address_line')) {
                $orderAttributes['shipping_address_line'] = $command->shippingAddressLine;
            }
            if ($needsShipping && Schema::hasColumn('orders', 'shipping_phone')) {
                $orderAttributes['shipping_phone'] = $command->shippingPhone;
            }

            $order = Order::query()->create($orderAttributes);

            foreach ($itemRows as $row) {
                $row['order_id'] = $order->id;
                OrderItem::query()->create($row);
            }

            $this->timeoutSnapshots->snapshotAtOrderCreation(
                $order,
                Product::query()->whereKey((int) ($order->primary_product_id ?? 0))->first(),
            );

            $this->recordOrderStateTransition(
                order: $order,
                from: OrderStatus::Draft,
                to: OrderStatus::PendingPayment,
                reasonCode: 'checkout_created',
                actorUserId: $command->buyerUserId,
                correlationId: $command->idempotencyKey,
            );

            $order->loadMissing(['orderItems', 'escrowAccount']);

            $response = array_merge(OrderResource::detail($order), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'idempotent_replay' => false,
            ]);
            $this->markIdempotencySucceeded($idem['idempotency'], $response);

            return $response;
        });
    }

    /**
     * Paginated orders visible to the viewer (buyer, line-item seller, or platform staff for all).
     *
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function listOrders(OrderListQuery $query): array
    {
        $builder = Order::query()->orderByDesc('id');
        if (! $query->viewerIsPlatformStaff) {
            $actor = User::query()->findOrFail($query->viewerUserId);
            $builder = (new OrderVisibilityService())->apply(
                $builder,
                $actor,
                $query->sellerOnly ? OrderVisibilityService::MODE_SELLER : OrderVisibilityService::MODE_BUYER,
            );
        }

        $page = max(1, $query->page);
        $perPage = min(100, max(1, $query->perPage));
        $total = (int) $builder->count();
        $rows = (clone $builder)->with(['orderItems', 'buyer', 'escrowAccount', 'paymentIntents.paymentTransactions', 'paymentTransactions.payment_intent'])->forPage($page, $perPage)->get();
        $items = [];
        foreach ($rows as $order) {
            $buyerName = trim((string) ($order->buyer?->display_name ?? $order->buyer?->name ?? $order->buyer?->email ?? ''));
            $latestIntent = $order->paymentIntents->sortByDesc('id')->first();
            $latestTxn = $order->paymentTransactions->sortByDesc('id')->first();
            $paymentMethod = null;
            if ($latestTxn !== null) {
                $paymentMethod = $latestTxn->raw_payload_json['method'] ?? $latestTxn->raw_payload_json['payment_method'] ?? null;
            }
            if ($paymentMethod === null && $latestIntent !== null) {
                $paymentMethod = $latestIntent->provider;
            }
            $items[] = [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'buyer_user_id' => $order->buyer_user_id,
                'seller_user_id' => $order->seller_user_id,
                'primary_product_id' => $order->primary_product_id,
                'product_type' => $order->product_type,
                'fulfillment_state' => $order->fulfillment_state,
                'buyer_name' => $buyerName !== '' ? $buyerName : 'Customer',
                'status' => $order->status->value,
                'payment_status' => $order->status->value,
                'payment_method' => $paymentMethod,
                'payment_provider' => $latestIntent?->provider,
                'escrow_state' => $order->escrowAccount?->state->value ?? 'unavailable',
                'currency' => $order->currency,
                'net_amount' => (string) $order->net_amount,
                'total_amount' => (string) $order->net_amount,
                'shipping_method' => $order->shipping_method,
                'shipping_address_id' => $order->shipping_address_id,
                'shipping_recipient_name' => $order->shipping_recipient_name,
                'shipping_address_line' => $order->shipping_address_line,
                'shipping_phone' => $order->shipping_phone,
                'courier_company' => $order->courier_company,
                'tracking_id' => $order->tracking_id,
                'tracking_url' => $order->tracking_url,
                'shipping_note' => $order->shipping_note,
                'shipped_at' => $order->shipped_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                'cancel_reason' => $order->cancel_reason,
                'can_cancel' => in_array($order->status->value, ['pending_payment', 'paid', 'paid_in_escrow', 'escrow_funded'], true),
                'timeout_state' => (new TimeoutAutomationService())->timerState($order),
                'item_count' => $order->orderItems->count(),
                'items' => $order->orderItems->map(static fn ($item): array => [
                    'title' => $item->title_snapshot,
                    'quantity' => $item->quantity,
                    'line_total' => (string) $item->line_total_snapshot,
                ])->values()->all(),
                'placed_at' => $order->placed_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
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

    /**
     * Seller-only paginated orders visible to the current seller account.
     *
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function listSellerOrders(int $viewerUserId, int $page = 1, int $perPage = 20): array
    {
        return $this->listOrders(new OrderListQuery(
            viewerUserId: $viewerUserId,
            viewerIsPlatformStaff: false,
            sellerOnly: true,
            page: $page,
            perPage: $perPage,
        ));
    }

    private function applyViewerOrderVisibility($builder, int $viewerUserId, bool $sellerOnly)
    {
        if ($sellerOnly) {
            return $builder->where('seller_user_id', $viewerUserId);
        }

        return $builder->where('buyer_user_id', $viewerUserId);
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

            $this->assertPaymentMutationActorAuthorized($order, $command->actorUserId, Ability::OrderMarkPendingPayment);

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
        $sellerUserId = (int) ($order->seller_user_id ?? 0);
        if ($sellerUserId <= 0) {
            $sellerProfileId = (int) $order->orderItems->first()->seller_profile_id;
            $seller = SellerProfile::query()->whereKey($sellerProfileId)->first();
            if ($seller === null) {
                throw new OrderValidationFailedException($order->id, 'seller_profile_not_found', [
                    'seller_profile_id' => $sellerProfileId,
                ]);
            }
            $sellerUserId = (int) $seller->user_id;
        }

        $this->walletLedgerService->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $sellerUserId,
            walletType: WalletType::Seller,
            currency: $currency,
        ));

        $wallet = Wallet::query()
            ->where('user_id', $sellerUserId)
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
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            $this->assertPaymentMutationActorAuthorized($order, $command->actorUserId, Ability::OrderMarkPaid);

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

            $this->assertPaymentCaptureAppliesToOrder($order, $intent, $txn);

            if (in_array($order->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Paid], true)) {
                throw new OrderValidationFailedException($order->id, 'order_already_paid_in_escrow', [
                    'current_status' => $order->status->value,
                    'payment_transaction_id' => $command->paymentTransactionId,
                ]);
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::EscrowFunded->value,
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
            $order->status = OrderStatus::EscrowFunded;
            if ($order->placed_at === null) {
                $order->placed_at = now();
            }
            $order->save();
            $this->timeoutSnapshots->snapshotAtEscrowFunded(
                $order,
                Product::query()->whereKey((int) ($order->primary_product_id ?? 0))->first(),
            );

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::EscrowFunded,
                reasonCode: 'payment_capture_funded_and_escrow_held',
                actorUserId: $command->actorUserId,
                correlationId: $command->correlationId ?? (string) Str::uuid(),
            );

            $this->notifySellerEscrowFunded($order);

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

    private function notifySellerEscrowFunded(Order $order): void
    {
        $sellerUserId = (int) ($order->seller_user_id ?? 0);
        if ($sellerUserId <= 0) {
            return;
        }

        $order->loadMissing(['orderItems', 'buyer']);
        $firstItem = $order->orderItems->first();
        $buyer = $order->buyer;
        $productTitle = (string) ($firstItem?->title_snapshot ?? 'Order item');
        $productType = (string) ($order->product_type ?? $firstItem?->product_type_snapshot ?? 'physical');
        $buyerLabel = (string) ($buyer?->name ?? $buyer?->email ?? ('Buyer #'.$order->buyer_user_id));

        app(TimeoutNotificationService::class)->notify(
            $sellerUserId,
            'escrow.order.seller.new',
            'You received a new order',
            $buyerLabel.' purchased '.$productTitle.'. Escrow is funded and delivery action is required.',
            [
                'order_id' => (int) $order->id,
                'order_number' => (string) ($order->order_number ?? $order->id),
                'buyer_user_id' => (int) $order->buyer_user_id,
                'buyer_name' => $buyerLabel,
                'buyer_email' => (string) ($buyer?->email ?? ''),
                'product_name' => $productTitle,
                'product_type' => $productType,
                'order_amount' => (string) $order->net_amount,
                'currency' => (string) $order->currency,
                'escrow_status' => 'funded',
                'action_required' => ProductType::normalize($productType)->requiresDeliveryChat()
                    ? 'submit_digital_delivery'
                    : 'prepare_shipment',
            ],
        );
    }

    public function payOrderWithWallet(int $orderId, int $actorUserId, ?string $correlationId = null): array
    {
        return DB::transaction(function () use ($orderId, $actorUserId, $correlationId): array {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($orderId, 'order_not_found', ['order_id' => $orderId]);
            }

            $this->assertPaymentMutationActorAuthorized($order, $actorUserId, Ability::OrderMarkPaid);

            if (in_array($order->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Paid], true)) {
                return OrderResource::detail($order->fresh());
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::EscrowFunded->value,
                );
            }

            $intent = PaymentIntent::query()->firstOrCreate([
                'order_id' => $order->id,
                'provider_intent_ref' => 'wallet_order_'.$order->id,
            ], [
                'uuid' => (string) Str::uuid(),
                'provider' => 'wallet',
                'status' => 'captured',
                'amount' => (string) $order->net_amount,
                'currency' => (string) $order->currency,
                'expires_at' => null,
            ]);
            $intent->status = 'captured';
            $intent->amount = (string) $order->net_amount;
            $intent->currency = (string) $order->currency;
            $intent->expires_at = null;
            $intent->save();

            $txn = PaymentTransaction::query()->firstOrCreate([
                'order_id' => $order->id,
                'provider_txn_ref' => 'wallet_capture_order_'.$order->id,
            ], [
                'uuid' => (string) Str::uuid(),
                'payment_intent_id' => $intent->id,
                'txn_type' => 'capture',
                'status' => 'success',
                'amount' => (string) $order->net_amount,
                'raw_payload_json' => [
                    'method' => 'wallet',
                    'order_id' => $order->id,
                ],
                'processed_at' => now(),
            ]);
            $txn->payment_intent_id = $intent->id;
            $txn->status = 'success';
            $txn->amount = (string) $order->net_amount;
            $txn->raw_payload_json = [
                'method' => 'wallet',
                'order_id' => $order->id,
            ];
            $txn->processed_at = now();
            $txn->save();

            $this->markPaid(new MarkOrderPaidCommand(
                orderId: $order->id,
                paymentTransactionId: $txn->id,
                correlationId: $correlationId ?? (string) Str::uuid(),
                actorUserId: $actorUserId,
            ));

            return OrderResource::detail($order->fresh());
        });
    }

    public function payOrderWithManualMethod(
        int $orderId,
        int $actorUserId,
        string $provider,
        string $providerReference,
        ?string $correlationId = null,
    ): array {
        $provider = strtolower(trim($provider));
        $providerReference = trim($providerReference);
        if (! in_array($provider, ['card', 'bkash', 'nagad', 'bank'], true)) {
            throw new OrderValidationFailedException($orderId, 'payment_provider_not_supported', [
                'provider' => $provider,
            ]);
        }
        if ($providerReference === '') {
            throw new OrderValidationFailedException($orderId, 'payment_reference_missing', [
                'provider' => $provider,
            ]);
        }

        $gateway = $this->paymentGatewayService->resolveForMethod($provider);
        if ($gateway === null) {
            throw new OrderValidationFailedException($orderId, 'payment_provider_not_enabled', [
                'provider' => $provider,
            ]);
        }

        return DB::transaction(function () use ($orderId, $actorUserId, $correlationId, $provider, $providerReference, $gateway): array {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($orderId, 'order_not_found', ['order_id' => $orderId]);
            }

            $this->assertPaymentMutationActorAuthorized($order, $actorUserId, Ability::OrderMarkPaid);

            if (in_array($order->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Paid], true)) {
                return OrderResource::detail($order->fresh());
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::EscrowFunded->value,
                );
            }

            $intent = PaymentIntent::query()->firstOrCreate([
                'order_id' => $order->id,
                'provider_intent_ref' => 'manual_'.$provider.'_'.$gateway->code.'_order_'.$order->id,
            ], [
                'uuid' => (string) Str::uuid(),
                'provider' => $provider,
                'status' => 'captured',
                'amount' => (string) $order->net_amount,
                'currency' => (string) $order->currency,
                'expires_at' => null,
            ]);
            $intent->provider = $provider;
            $intent->status = 'captured';
            $intent->amount = (string) $order->net_amount;
            $intent->currency = (string) $order->currency;
            $intent->expires_at = null;
            $intent->save();

            $referenceHash = substr(hash('sha256', $providerReference), 0, 12);
            $txn = PaymentTransaction::query()->firstOrCreate([
                'order_id' => $order->id,
                'provider_txn_ref' => 'manual_'.$provider.'_'.$gateway->code.'_capture_order_'.$order->id.'_'.$referenceHash,
            ], [
                'uuid' => (string) Str::uuid(),
                'payment_intent_id' => $intent->id,
                'txn_type' => 'capture',
                'status' => 'success',
                'amount' => (string) $order->net_amount,
                'raw_payload_json' => [
                    'method' => $provider,
                    'gateway_code' => $gateway->code,
                    'gateway_name' => $gateway->name,
                    'gateway_driver' => $gateway->driver,
                    'provider_reference' => $providerReference,
                    'order_id' => $order->id,
                ],
                'processed_at' => now(),
            ]);
            $txn->payment_intent_id = $intent->id;
            $txn->status = 'success';
            $txn->amount = (string) $order->net_amount;
            $txn->raw_payload_json = [
                'method' => $provider,
                'gateway_code' => $gateway->code,
                'gateway_name' => $gateway->name,
                'gateway_driver' => $gateway->driver,
                'provider_reference' => $providerReference,
                'order_id' => $order->id,
            ];
            $txn->processed_at = now();
            $txn->save();

            $this->markPaid(new MarkOrderPaidCommand(
                orderId: $order->id,
                paymentTransactionId: $txn->id,
                correlationId: $correlationId ?? (string) Str::uuid(),
                actorUserId: $actorUserId,
            ));

            return OrderResource::detail($order->fresh());
        });
    }


    public function advanceFulfillment(AdvanceOrderFulfillmentCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            $this->assertFulfillmentActorAuthorized($order, $command->actorUserId);

            if (in_array($order->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow], true)) {
                $this->fulfillmentService->start($order, $command->actorUserId, $command->correlationId);

                return [
                    'order_id' => $order->id,
                    'status' => OrderStatus::Processing->value,
                    'fulfillment_state' => 'in_progress',
                    'idempotent_replay' => false,
                ];
            }

            if ($order->status === OrderStatus::Processing && ProductType::normalize($order->product_type)->requiresDeliveryChat()) {
                $this->fulfillmentService->submitDelivery($order, $command->actorUserId, null, $command->correlationId);

                return [
                    'order_id' => $order->id,
                    'status' => OrderStatus::BuyerReview->value,
                    'fulfillment_state' => 'buyer_review',
                    'idempotent_replay' => false,
                ];
            }

            if ($order->status !== OrderStatus::Processing || ProductType::normalize($order->product_type) !== ProductType::Physical) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::Processing->value,
                );
            }

            return [
                'order_id' => $order->id,
                'status' => $order->status->value,
                'idempotent_replay' => true,
            ];
        });
    }

    public function submitDelivery(int $orderId, int $actorUserId, ?string $note = null, ?string $correlationId = null): array
    {
        return DB::transaction(function () use ($orderId, $actorUserId, $note, $correlationId): array {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($orderId, 'order_not_found', ['order_id' => $orderId]);
            }

            $this->assertFulfillmentActorAuthorized($order, $actorUserId);
            $this->fulfillmentService->submitDelivery($order, $actorUserId, $note, $correlationId);

            return OrderResource::detail($order->fresh());
        });
    }

    public function addShippingDetails(AddOrderShippingDetailsCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            $this->assertFulfillmentActorAuthorized($order, $command->actorUserId);

            if (ProductType::normalize($order->product_type) !== ProductType::Physical) {
                throw new OrderValidationFailedException($order->id, 'shipping_only_allowed_for_physical_orders', [
                    'product_type' => $order->product_type,
                ]);
            }

            if (! in_array($order->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Processing, OrderStatus::ShippedOrDelivered], true)) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::ShippedOrDelivered->value,
                );
            }

            $from = $order->status;
            $order->status = OrderStatus::BuyerReview;
            $order->fulfillment_state = 'buyer_review';
            if ($this->hasOrderColumn('courier_company')) {
                $order->courier_company = $command->courierCompany;
            }
            if ($this->hasOrderColumn('tracking_id')) {
                $order->tracking_id = $command->trackingId;
            }
            if ($this->hasOrderColumn('tracking_url')) {
                $trackingUrl = trim((string) ($command->trackingUrl ?? ''));
                $order->tracking_url = $trackingUrl !== '' ? $trackingUrl : sprintf('https://tracking.sellova.com/%s', rawurlencode($command->trackingId));
            }
            if ($this->hasOrderColumn('shipping_note')) {
                $order->shipping_note = $command->shippingNote;
            }
            if ($this->hasOrderColumn('shipped_at') && $order->shipped_at === null) {
                try {
                    $order->shipped_at = $command->shippedAtIso !== null && trim($command->shippedAtIso) !== ''
                        ? Carbon::parse($command->shippedAtIso)
                        : now();
                } catch (\Throwable) {
                    $order->shipped_at = now();
                }
            }
            $order->save();

            $order->loadMissing('orderItems');
            foreach ($order->orderItems as $item) {
                if ($item->delivery_state !== 'delivered') {
                    $item->delivery_state = 'in_progress';
                    $item->save();
                }
            }
            $this->timeoutSnapshots->snapshotAtDeliverySubmitted(
                $order,
                Product::query()->whereKey((int) ($order->primary_product_id ?? 0))->first(),
            );

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::BuyerReview,
                reasonCode: 'seller_shipped',
                actorUserId: $command->actorUserId,
                correlationId: $command->correlationId ?? (string) Str::uuid(),
            );

            return OrderResource::detail($order);
        });
    }

    public function cancelOrder(CancelOrderCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            $this->assertCancellationActorAuthorized($order, $command->actorUserId);

            if ($order->status === OrderStatus::Cancelled) {
                return OrderResource::detail($order->fresh());
            }

            if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::PaidInEscrow, OrderStatus::EscrowFunded], true)) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::Cancelled->value,
                );
            }

            if (in_array($order->status, [OrderStatus::PaidInEscrow, OrderStatus::EscrowFunded], true)) {
                $order->loadMissing('escrowAccount');
                $escrow = $order->escrowAccount;
                if ($escrow === null) {
                    throw new OrderValidationFailedException($order->id, 'escrow_account_not_found', ['order_id' => $order->id]);
                }
                $this->escrowService->refundEscrow(new RefundEscrowCommand(
                    escrowAccountId: (int) $escrow->id,
                    refundAmount: null,
                    idempotencyKey: 'order:'.$order->id.':cancel:refund',
                ));
            }

            $from = $order->status;
            $order->status = OrderStatus::Cancelled;
            if ($this->hasOrderColumn('cancelled_at') && $order->cancelled_at === null) {
                $order->cancelled_at = now();
            }
            if ($this->hasOrderColumn('cancel_reason')) {
                $reason = trim((string) ($command->reason ?? ''));
                $order->cancel_reason = $reason !== '' ? $reason : 'Cancelled by buyer before fulfillment started.';
            }
            $order->save();

            $order->loadMissing('orderItems');
            foreach ($order->orderItems as $item) {
                if ($item->delivery_state !== 'delivered') {
                    $item->delivery_state = 'not_started';
                    $item->save();
                }
            }

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::Cancelled,
                reasonCode: 'buyer_cancelled_before_fulfillment',
                actorUserId: $command->actorUserId,
                correlationId: $command->correlationId ?? (string) Str::uuid(),
            );

            return OrderResource::detail($order->fresh());
        });
    }

    public function completeOrder(CompleteOrderCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($command->orderId, 'order_not_found', ['order_id' => $command->orderId]);
            }

            $this->assertCompletionActorAuthorized($order, $command->actorUserId);

            if ($order->status === OrderStatus::Completed) {
                return [
                    'order_id' => $order->id,
                    'status' => $order->status->value,
                    'idempotent_replay' => true,
                ];
            }

            if (! in_array($order->status, [OrderStatus::BuyerReview, OrderStatus::DeliverySubmitted, OrderStatus::ShippedOrDelivered], true)) {
                throw new InvalidOrderStateTransitionException(
                    orderId: $order->id,
                    fromStatus: $order->status->value,
                    toStatus: OrderStatus::Completed->value,
                );
            }

            $order->loadMissing('escrowAccount');
            $escrow = $order->escrowAccount;
            if ($escrow === null) {
                throw new OrderValidationFailedException($order->id, 'escrow_account_not_found', ['order_id' => $order->id]);
            }

            $release = $this->escrowReleaseService->releaseAfterBuyerConfirmation(
                order: $order,
                actorUserId: $command->actorUserId,
                idempotencyKey: 'order:'.$order->id.':complete:release',
            );

            $from = $order->status;
            $order->status = OrderStatus::Completed;
            if ($order->completed_at === null) {
                $order->completed_at = now();
            }
            if ($this->hasOrderColumn('delivered_at') && $order->delivered_at === null) {
                $order->delivered_at = now();
            }
            $order->save();

            $order->loadMissing('orderItems');
            foreach ($order->orderItems as $item) {
                if ($item->delivery_state !== 'delivered') {
                    $item->delivery_state = 'delivered';
                    $item->save();
                }
            }

            $this->recordOrderStateTransition(
                order: $order,
                from: $from,
                to: OrderStatus::Completed,
                reasonCode: 'buyer_confirmed_delivery',
                actorUserId: $command->actorUserId,
                correlationId: $command->correlationId ?? (string) Str::uuid(),
            );

            return [
                'order_id' => $order->id,
                'status' => $order->status->value,
                'escrow_account_id' => (int) $escrow->id,
                'escrow_state' => (string) ($release['state'] ?? $escrow->state->value),
                'idempotent_replay' => false,
            ];
        });
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

            if (! in_array($order->status, [OrderStatus::PaidInEscrow, OrderStatus::EscrowFunded, OrderStatus::Processing, OrderStatus::DeliverySubmitted, OrderStatus::BuyerReview], true)) {
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

            $allowed = [OrderStatus::Refunded, OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Completed];
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
     * @param  non-empty-string  $action  {@see Ability::OrderMarkPendingPayment} or {@see Ability::OrderMarkPaid}
     */
    private function assertPaymentMutationActorAuthorized(Order $order, ?int $actorUserId, string $action): void
    {
        if ($actorUserId === null) {
            throw new OrderValidationFailedException($order->id, 'payment_mutation_actor_required', []);
        }

        $actor = User::query()->find($actorUserId);
        if ($actor === null) {
            throw new OrderValidationFailedException($order->id, 'actor_user_not_found', ['actor_user_id' => $actorUserId]);
        }

        $policy = new OrderPolicy();
        $allowed = match ($action) {
            Ability::OrderMarkPendingPayment => $policy->markPendingPayment($actor, $order),
            Ability::OrderMarkPaid => $policy->markPaid($actor, $order),
            default => false,
        };

        if (! $allowed) {
            throw new DomainAuthorizationDeniedException($action, $actorUserId);
        }
    }

    private function assertFulfillmentActorAuthorized(Order $order, int $actorUserId): void
    {
        $actor = User::query()->find($actorUserId);
        if ($actor === null) {
            throw new OrderValidationFailedException($order->id, 'actor_user_not_found', ['actor_user_id' => $actorUserId]);
        }

        if ($actor->isPlatformStaff()) {
            return;
        }

        if (! OrderParticipant::isSellerParticipant($actor, $order)) {
            throw new DomainAuthorizationDeniedException('order.advanceFulfillment', $actorUserId);
        }
    }

    private function assertCompletionActorAuthorized(Order $order, int $actorUserId): void
    {
        $actor = User::query()->find($actorUserId);
        if ($actor === null) {
            throw new OrderValidationFailedException($order->id, 'actor_user_not_found', ['actor_user_id' => $actorUserId]);
        }

        if ($actor->isPlatformStaff()) {
            return;
        }

        if (! OrderParticipant::isBuyer($actor, $order)) {
            throw new DomainAuthorizationDeniedException('order.complete', $actorUserId);
        }
    }

    private function assertCancellationActorAuthorized(Order $order, int $actorUserId): void
    {
        $actor = User::query()->find($actorUserId);
        if ($actor === null) {
            throw new OrderValidationFailedException($order->id, 'actor_user_not_found', ['actor_user_id' => $actorUserId]);
        }

        if ($actor->isPlatformStaff()) {
            return;
        }

        if (! OrderParticipant::isBuyer($actor, $order)) {
            throw new DomainAuthorizationDeniedException('order.cancel', $actorUserId);
        }
    }

    private function hasOrderColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('orders', $column);
        } catch (\Throwable) {
            return false;
        }
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

    private function normalizeShippingMethod(string $shippingMethod): string
    {
        $method = strtolower(trim($shippingMethod));

        return in_array($method, ['standard', 'express'], true) ? $method : 'standard';
    }

    private function generateOrderNumber(string $idempotencyKey): string
    {
        return 'ORD-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 10));
    }

    /**
     * @return array{idempotency: IdempotencyKey, replay: bool}
     */
    private function claimOrderCreateIdempotency(string $key, string $requestHash): array
    {
        $scope = 'order_create';
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
        if (! in_array($order->status, [OrderStatus::EscrowFunded, OrderStatus::PaidInEscrow, OrderStatus::Paid], true)) {
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

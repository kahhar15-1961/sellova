# HTTP → Domain pipeline (contracts)

Controllers are **not** generated yet. This document defines how each future endpoint maps **validated HTTP input** to **commands**, **services**, and **domain exceptions**.

## Conventions

| Layer | Responsibility |
|--------|----------------|
| **FormRequest** | HTTP validation only (format, required fields, rate limits). No money math, no state authority. |
| **Command / DTO** | Typed input contract for a single service use case. Immutable `readonly` classes; lifecycle fields use `App\Domain\Enums\*` backed enums where applicable. |
| **Service** | Authorization, idempotency, DB transactions, state machines, invariant checks, orchestration. |
| **Model** | Persistence and relationships only. |
| **Domain exception** | Explicit failure surfaced to the exception handler / API layer. |

## Exception mapping (by concern)

| Concern | Exception class |
|---------|-----------------|
| Generic invalid transition | `InvalidDomainStateTransitionException` |
| Order status machine | `InvalidOrderStateTransitionException` |
| Escrow state machine | `InvalidEscrowStateTransitionException` |
| RBAC / policy | `DomainAuthorizationDeniedException` |
| Wallet balance | `InsufficientWalletBalanceException` |
| Escrow release rules | `EscrowReleaseConflictException` |
| Idempotency key reuse / mismatch | `IdempotencyConflictException` |
| Withdrawal rules | `WithdrawalValidationFailedException` |
| Dispute outcome vs escrow/ledger | `DisputeResolutionConflictException` |
| Catalog / product rules | `ProductValidationFailedException` |
| Checkout / order totals | `OrderValidationFailedException` |

---

## Auth

| Future controller action | FormRequest (suggested) | Command | Service method | Typical domain exceptions |
|--------------------------|-------------------------|---------|----------------|---------------------------|
| POST register buyer | `RegisterBuyerRequest` | `RegisterBuyerCommand` | `AuthService::registerBuyer` | (validation stays in FormRequest); duplicate email → app-level later |
| POST register seller | `RegisterSellerRequest` | `RegisterSellerCommand` | `AuthService::registerSeller` | — |
| POST login | `LoginRequest` | `LoginCommand` | `AuthService::login` | `DomainAuthorizationDeniedException` |
| POST logout | `LogoutRequest` | `LogoutCommand` | `AuthService::logout` | `DomainAuthorizationDeniedException` |
| POST refresh | `RefreshTokenRequest` | `RefreshTokenCommand` | `AuthService::refreshToken` | `DomainAuthorizationDeniedException` |

---

## User / Seller

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| PATCH profile | `UpdateUserProfileRequest` | `UpdateUserProfileCommand` | `UserSellerService::updateProfile` | `DomainAuthorizationDeniedException` |
| POST seller profile | `CreateSellerProfileRequest` | `CreateSellerProfileCommand` | `UserSellerService::createSellerProfile` | `DomainAuthorizationDeniedException`; duplicate seller profile / invalid user state → `InvalidDomainStateTransitionException` (or dedicated exception in implementation) |
| POST KYC documents | `SubmitKycRequest` | `SubmitKycCommand` | `UserSellerService::submitKyc` | `DomainAuthorizationDeniedException` |
| POST KYC review (admin) | `ReviewKycRequest` | `ReviewKycCommand` | `UserSellerService::reviewKyc` | `InvalidDomainStateTransitionException`, `DomainAuthorizationDeniedException` |

---

## Product

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| POST product | `CreateProductRequest` | `CreateProductCommand` | `ProductService::createProduct` | `ProductValidationFailedException`, `DomainAuthorizationDeniedException` |
| PUT product | `UpdateProductRequest` | `UpdateProductCommand` | `ProductService::updateProduct` | `ProductValidationFailedException`, `DomainAuthorizationDeniedException` |
| POST publish | `PublishProductRequest` | `PublishProductCommand` | `ProductService::publishProduct` | `InvalidDomainStateTransitionException`, `DomainAuthorizationDeniedException` |
| POST inventory adjust | `AdjustInventoryRequest` | `AdjustInventoryCommand` | `ProductService::adjustInventory` | `ProductValidationFailedException`, `DomainAuthorizationDeniedException` |

---

## Order (financial-critical)

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| POST checkout / create order | `CreateOrderRequest` | `CreateOrderCommand` | `OrderService::createOrder` | `OrderValidationFailedException`, `IdempotencyConflictException`, `DomainAuthorizationDeniedException` |
| POST mark pending payment | `MarkOrderPendingPaymentRequest` | `MarkOrderPendingPaymentCommand` | `OrderService::markPendingPayment` | `InvalidOrderStateTransitionException`, `DomainAuthorizationDeniedException` |
| POST mark paid (internal / webhook handler) | `MarkOrderPaidRequest` | `MarkOrderPaidCommand` | `OrderService::markPaid` | `InvalidOrderStateTransitionException`, `OrderValidationFailedException`, `IdempotencyConflictException` |
| PATCH fulfillment | `AdvanceOrderFulfillmentRequest` | `AdvanceOrderFulfillmentCommand` | `OrderService::advanceFulfillment` | `InvalidOrderStateTransitionException` |
| POST complete | `CompleteOrderRequest` | `CompleteOrderCommand` | `OrderService::completeOrder` | `InvalidOrderStateTransitionException`, `EscrowReleaseConflictException` |

**Transaction boundary:** entire `OrderService` mutating methods run inside `DB::transaction()` with row locks where specified in implementation.

---

## Escrow (financial-critical)

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| (internal) create escrow | `CreateEscrowForOrderRequest` | `CreateEscrowForOrderCommand` | `EscrowService::createEscrowForOrder` | `OrderValidationFailedException`, `IdempotencyConflictException` |
| POST hold | `HoldEscrowRequest` | `HoldEscrowCommand` | `EscrowService::holdEscrow` | `InvalidEscrowStateTransitionException`, `IdempotencyConflictException` |
| POST release | `ReleaseEscrowRequest` | `ReleaseEscrowCommand` | `EscrowService::releaseEscrow` | `InvalidEscrowStateTransitionException`, `EscrowReleaseConflictException`, `IdempotencyConflictException` |
| POST refund | `RefundEscrowRequest` | `RefundEscrowCommand` | `EscrowService::refundEscrow` | `InvalidEscrowStateTransitionException`, `EscrowReleaseConflictException`, `IdempotencyConflictException` |
| POST link dispute | `MarkEscrowUnderDisputeRequest` | `MarkEscrowUnderDisputeCommand` | `EscrowService::markUnderDispute` | `InvalidEscrowStateTransitionException`, `DisputeResolutionConflictException` |

---

## Wallet / Ledger (financial-critical)

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| POST ensure wallet | `CreateWalletIfMissingRequest` | `CreateWalletIfMissingCommand` | `WalletLedgerService::createWalletIfMissing` | `DomainAuthorizationDeniedException` |
| POST hold | `PlaceWalletHoldRequest` | `PlaceWalletHoldCommand` | `WalletLedgerService::placeHold` | `InsufficientWalletBalanceException`, `IdempotencyConflictException` |
| POST release hold | `ReleaseWalletHoldRequest` | `ReleaseWalletHoldCommand` | `WalletLedgerService::releaseHold` | `InvalidDomainStateTransitionException`, `DomainAuthorizationDeniedException` |
| POST ledger batch | `PostLedgerBatchRequest` | `PostLedgerBatchCommand` | `WalletLedgerService::postLedgerBatch` | `InsufficientWalletBalanceException`, `IdempotencyConflictException`, `OrderValidationFailedException` (if linked) |
| POST reverse batch | `ReverseLedgerBatchRequest` | `ReverseLedgerBatchCommand` | `WalletLedgerService::reverseLedgerBatch` | `InvalidDomainStateTransitionException`, `DomainAuthorizationDeniedException` |
| GET balances (materialized) | `ComputeWalletBalancesRequest` | `ComputeWalletBalancesCommand` | `WalletLedgerService::computeWalletBalances` | `DomainAuthorizationDeniedException` |

**Idempotency:** `PostLedgerBatchCommand::$idempotencyKey` and any command carrying `idempotencyKey` for external retries.

**Typed commands (examples):** `AdvanceOrderFulfillmentCommand::$toState` is `OrderStatus`; `ResolveDisputeCommand::$outcome` is `DisputeResolutionOutcome`; `ReviewWithdrawalCommand::$decision` is `WithdrawalReviewDecision`; wallet ledger commands use `WalletType`, `WalletHoldType`, `LedgerPostingEventName`, and `LedgerPostingLine` carries `WalletLedgerEntrySide` / `WalletLedgerEntryType`.

**Negative balance rule (explicit):**
- Default: available balance MUST NOT go below zero.
- Exception: `WalletLedgerEntryType::AdjustmentDebit` may intentionally overdraw available balance for corrective/manual operations.
- Policy source: `App\Domain\Policy\WalletNegativeBalancePolicy`.

---

## Withdrawal (financial-critical)

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| POST request | `RequestWithdrawalRequest` | `RequestWithdrawalCommand` | `WithdrawalService::requestWithdrawal` | `WithdrawalValidationFailedException`, `InsufficientWalletBalanceException`, `IdempotencyConflictException`, `DomainAuthorizationDeniedException` |
| POST review (admin) | `ReviewWithdrawalRequest` | `ReviewWithdrawalCommand` | `WithdrawalService::reviewWithdrawal` | `WithdrawalValidationFailedException`, `InvalidDomainStateTransitionException`, `DomainAuthorizationDeniedException` |
| POST submit payout | `SubmitPayoutRequest` | `SubmitPayoutCommand` | `WithdrawalService::submitPayout` | `WithdrawalValidationFailedException`, `InvalidDomainStateTransitionException` |
| POST confirm payout | `ConfirmPayoutRequest` | `ConfirmPayoutCommand` | `WithdrawalService::confirmPayout` | `WithdrawalValidationFailedException`, `IdempotencyConflictException` |
| POST fail payout | `FailPayoutRequest` | `FailPayoutCommand` | `WithdrawalService::failPayout` | `WithdrawalValidationFailedException` |

---

## Dispute (financial-critical)

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| POST open | `OpenDisputeRequest` | `OpenDisputeCommand` | `DisputeService::openDispute` | `OrderValidationFailedException`, `InvalidOrderStateTransitionException`, `DisputeResolutionConflictException`, `DomainAuthorizationDeniedException` |
| POST evidence | `SubmitDisputeEvidenceRequest` | `SubmitDisputeEvidenceCommand` | `DisputeService::submitEvidence` | `DisputeResolutionConflictException`, `InvalidDomainStateTransitionException` |
| POST move review | `MoveDisputeToReviewRequest` | `MoveDisputeToReviewCommand` | `DisputeService::moveToReview` | `InvalidDomainStateTransitionException`, `DomainAuthorizationDeniedException` |
| POST resolve (admin) | `ResolveDisputeRequest` | `ResolveDisputeCommand` | `DisputeService::resolveDispute` | `DisputeResolutionConflictException`, `EscrowReleaseConflictException`, `InsufficientWalletBalanceException`, `InvalidEscrowStateTransitionException` |

---

## Membership (financial-critical when payment/fees apply)

| Future controller action | FormRequest | Command | Service method | Typical domain exceptions |
|----------------------------|-------------|---------|----------------|---------------------------|
| POST plan (admin) | `CreateMembershipPlanRequest` | `CreateMembershipPlanCommand` | `MembershipService::createPlan` | `DomainAuthorizationDeniedException`; invalid plan payload → dedicated validation exception in implementation (optional subclass of `DomainException`) |
| POST subscribe | `SubscribeSellerToMembershipRequest` | `SubscribeSellerToMembershipCommand` | `MembershipService::subscribeSeller` | `IdempotencyConflictException`, `OrderValidationFailedException` (payment), `DomainAuthorizationDeniedException` |
| POST renew | `RenewMembershipSubscriptionRequest` | `RenewMembershipSubscriptionCommand` | `MembershipService::renewSubscription` | `IdempotencyConflictException`, `InvalidDomainStateTransitionException` |
| POST cancel | `CancelMembershipSubscriptionRequest` | `CancelMembershipSubscriptionCommand` | `MembershipService::cancelSubscription` | `InvalidDomainStateTransitionException` |
| GET resolve commission (internal) | `ResolveCommissionRuleRequest` | `ResolveCommissionRuleCommand` | `MembershipService::resolveCommissionRule` | `DomainAuthorizationDeniedException` |

---

## File locations

- **Commands:** `app/Domain/Commands/{Auth,UserSeller,Product,Order,Escrow,WalletLedger,Withdrawal,Dispute,Membership}/`
- **Enums:** `app/Domain/Enums/` (order, escrow, withdrawal, dispute, membership, wallet ledger, idempotency, etc.)
- **Shared value objects:** `app/Domain/Value/`
- **Exceptions:** `app/Domain/Exceptions/`
- **Services:** `app/Services/...` (signatures updated to accept commands)

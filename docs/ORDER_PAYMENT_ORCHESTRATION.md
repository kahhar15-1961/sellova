# Order payment orchestration

This document describes how `OrderService` couples PSP-settled payment data to escrow creation and wallet holds, and where multi-seller checkout is intentionally rejected.

## Flow (single-seller)

1. **Draft → pending payment** — `OrderService::markPendingPayment` locks the order, requires `orders.status = draft`, transitions to `pending_payment`, and appends `order_state_transitions`.
2. **Pending payment → paid_in_escrow** — `OrderService::markPaid` runs inside `DB::transaction()`:
   - Claims idempotency scope `order_mark_paid` (see `OrderService::claimOrderMarkPaidIdempotency`).
   - Locks `payment_transactions` → `payment_intents` → `orders` (child-to-parent order reduces deadlock risk).
   - Validates the capture transaction (`txn_type = capture`, `status = success`) belongs to the order and that the intent is `captured`, with strict amount/currency alignment to `orders.net_amount` / `orders.currency`.
   - Rejects **already settled** orders (`paid_in_escrow` or legacy `paid`) with `OrderValidationFailedException` (`order_already_paid_in_escrow`) before any wallet or escrow mutation (duplicate capture / double `markPaid` protection).
   - Requires `pending_payment` for the happy path; any other non-terminal state uses `InvalidOrderStateTransitionException` toward `paid_in_escrow`.
   - **Multi-seller guard** — `OrderService::assertSingleSellerOrderForEscrow` requires exactly one distinct `order_items.seller_profile_id`. If there are zero items or more than one seller, it throws `OrderValidationFailedException` with `reasonCode = multi_seller_escrow_not_supported`. This is the approved orchestration choke point until marketplace split-settlement exists.
   - Resolves buyer/seller wallets via `resolveBuyerWalletId` / `resolveSellerWalletId` (each uses `WalletLedgerService::createWalletIfMissing` when needed).
   - **Buyer funding** — `postFundingForOrderFromCapturedPayment` posts a `WalletLedgerService::postLedgerBatch` with `LedgerPostingEventName::PaymentCapture` (ledger line: `deposit_credit` credit) keyed by `order:{id}:payment_capture_funding:txn:{txnId}` so capture credits are idempotent and auditable.
   - **Escrow** — `EscrowService::createEscrowForOrder` then `holdEscrow` with deterministic idempotency keys derived from `orderId` + `paymentTransactionId`.
   - Sets `orders.status = paid_in_escrow`, sets `placed_at` when still null, records `order_state_transitions` (`payment_capture_funded_and_escrow_held`), and marks the orchestration idempotency row succeeded.

## Multi-seller blocking (explicit)

| Location | Behavior |
|----------|-----------|
| `OrderService::assertSingleSellerOrderForEscrow` | **Canonical block** for checkout payment → escrow orchestration. Rejects `>1` distinct seller on line items. |
| `EscrowService` (release path) | Still enforces `multi_seller_release_not_supported_yet` when resolving wallets for settlement; that is separate from payment capture orchestration. |

## Ledger coupling

- Capture settlement is modeled **inside** `markPaid` as a `payment_capture` ledger batch (buyer wallet credit) immediately before escrow create/hold, all in one DB transaction so failures roll back together.

## Controllers

HTTP entrypoints are intentionally out of scope; adapters should call `OrderService` commands from application services or jobs.

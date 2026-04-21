# Order payment orchestration

This document describes how `OrderService` couples PSP-settled payment data to escrow creation and wallet holds, and where multi-seller checkout is intentionally rejected.

## Flow (single-seller)

1. **Draft → pending payment** — `OrderService::markPendingPayment` locks the order, requires `orders.status = draft`, transitions to `pending_payment`, and appends `order_state_transitions`.
2. **Pending payment → paid + escrow** — `OrderService::markPaid` runs inside `DB::transaction()`:
   - Claims idempotency scope `order_mark_paid` (see `OrderService::claimOrderMarkPaidIdempotency`).
   - Locks `payment_transactions` → `payment_intents` → `orders` (FK order avoids deadlocks when composed with other writers).
   - Validates the capture transaction (`txn_type = capture`, `status = success`) belongs to the order and that the intent is `captured`, with strict amount/currency alignment to `orders.net_amount` / `orders.currency`.
   - **Multi-seller guard** — `OrderService::assertSingleSellerOrderForEscrow` requires exactly one distinct `order_items.seller_profile_id`. If there are zero items or more than one seller, it throws `OrderValidationFailedException` with `reasonCode = multi_seller_escrow_not_supported`. This is the approved orchestration choke point until marketplace split-settlement exists.
   - Ensures buyer and seller wallets exist via `WalletLedgerService::createWalletIfMissing` (no ledger bypass; no funds invented).
   - Calls `EscrowService::createEscrowForOrder` then `EscrowService::holdEscrow` with deterministic idempotency keys derived from `orderId` + `paymentTransactionId` (see `OrderService::escrowCreateIdempotencyKey` / `escrowHoldIdempotencyKey`).
   - Sets `orders.status = paid`, optionally sets `placed_at` if still null, records `order_state_transitions`, and marks the orchestration idempotency row succeeded.

## Multi-seller blocking (explicit)

| Location | Behavior |
|----------|-----------|
| `OrderService::assertSingleSellerOrderForEscrow` | **Canonical block** for checkout payment → escrow orchestration. Rejects `>1` distinct seller on line items. |
| `EscrowService` (release path) | Still enforces `multi_seller_release_not_supported_yet` when resolving wallets for settlement; that is separate from payment capture orchestration. |

## Preconditions (not implemented here)

- Funds must already be available in the buyer wallet for the escrow hold debit (typically a deposit/capture posting from a payment adapter using `WalletLedgerService` in its own transaction, **before** or **within** the same unit of work as `markPaid`, depending on product wiring). `OrderService` does not post PSP settlement credits itself.

## Controllers

HTTP entrypoints are intentionally out of scope; adapters should call `OrderService` commands from application services or jobs.

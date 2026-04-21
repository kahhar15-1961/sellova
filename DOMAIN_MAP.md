# Sellova Domain Map

## Scope and Intent

This document defines the implementation-ready domain model for Sellova, including core entities, relationships, module boundaries, state machines, invariants, and risk controls. It is the authoritative business architecture reference before application-layer coding.

---

## 1) Core Domain Entities

### Identity and Access
- `User`: system actor (buyer, seller, admin, moderator, support).
- `Role`: role catalog for RBAC.
- `Permission`: permission catalog for RBAC.
- `UserRole`: user-role assignment.
- `RolePermission`: role-permission assignment.

### Seller Trust
- `SellerProfile`: seller business identity and operational status.
- `KycVerification`: seller verification lifecycle.
- `KycDocument`: KYC evidence artifacts.

### Catalog and Storefront
- `Storefront`: seller public storefront.
- `Category`: product taxonomy (hierarchical).
- `Product`: sellable listing (`physical`, `digital`, `manual_delivery`).
- `ProductVariant`: SKU-level variation.
- `InventoryRecord`: stock and reservation counts.

### Cart and Checkout
- `Cart`: buyer shopping cart.
- `CartItem`: selected line items with price snapshots.

### Orders and Payments
- `Order`: buyer purchase contract and lifecycle.
- `OrderItem`: immutable line snapshots within an order.
- `OrderStateTransition`: transition audit trail.
- `PaymentIntent`: payment provider intent object.
- `PaymentTransaction`: provider transaction records.
- `PaymentWebhookEvent`: external callback inbox.

### Escrow
- `EscrowAccount`: escrow container per order.
- `EscrowEvent`: escrow lifecycle event history.

### Wallet and Ledger
- `Wallet`: user/platform currency wallet.
- `WalletHold`: reserved funds (escrow/withdrawal/risk).
- `WalletLedgerBatch`: idempotent posting batch.
- `WalletLedgerEntry`: append-only ledger line.
- `WalletBalanceSnapshot`: periodic balance materialization.

### Withdrawals
- `WithdrawalRequest`: payout request lifecycle.
- `WithdrawalTransaction`: payout provider attempts/results.
- `PayoutAccount`: seller payout destination.

### Disputes
- `DisputeCase`: dispute workflow root.
- `DisputeEvidence`: submitted proof artifacts.
- `DisputeDecision`: final adjudication and split.

### Membership and Monetization
- `MembershipPlan`: plan definitions and pricing.
- `MembershipSubscription`: seller membership lifecycle.
- `CommissionRule`: fee policy resolution rules.

### Reputation, Notifications, and Compliance
- `Review`: buyer rating and feedback.
- `Notification`: outbound communications.
- `AuditLog`: immutable security/compliance trail.
- `IdempotencyKey`: duplicate mutation prevention.
- `OutboxEvent`: reliable domain-event publication.

---

## 2) Relationships Between Entities

- `User` 1:0..1 `SellerProfile`
- `User` 1:N `Wallet`
- `User` N:M `Role` via `UserRole`
- `Role` N:M `Permission` via `RolePermission`
- `SellerProfile` 1:N `KycVerification`
- `KycVerification` 1:N `KycDocument`
- `SellerProfile` 1:1 `Storefront`
- `Storefront` 1:N `Product`
- `Category` self-parent hierarchy; `Category` 1:N `Product`
- `Product` 1:N `ProductVariant`
- `InventoryRecord` references exactly one of (`Product` xor `ProductVariant`)
- `User` 1:N `Cart`; `Cart` 1:N `CartItem`
- `Order` 1:N `OrderItem`
- `Order` 1:N `PaymentIntent`; `PaymentIntent` 1:N `PaymentTransaction`
- `Order` 1:1 `EscrowAccount`; `EscrowAccount` 1:N `EscrowEvent`
- `Wallet` 1:N `WalletHold`
- `WalletLedgerBatch` 1:N `WalletLedgerEntry`
- `Wallet` 1:N `WalletLedgerEntry`
- `SellerProfile` 1:N `WithdrawalRequest`; `WithdrawalRequest` 1:N `WithdrawalTransaction`
- `Order` 1:N `DisputeCase`; `DisputeCase` 1:N `DisputeEvidence`; `DisputeCase` 1:1 `DisputeDecision`
- `SellerProfile` 1:N `MembershipSubscription`; `MembershipSubscription` N:1 `MembershipPlan`
- `OrderItem` 1:1 `Review` (one review per purchased item)
- `User` 1:N `Notification`
- `AuditLog` and `OutboxEvent` are cross-cutting.

---

## 3) Module Boundaries

### Identity and Access Module
- Owns authentication, authorization, RBAC, account status.
- Tables: `users`, `roles`, `permissions`, `user_roles`, `role_permissions`.

### Seller Trust Module
- Owns seller onboarding and KYC workflows.
- Tables: `seller_profiles`, `kyc_verifications`, `kyc_documents`.

### Catalog Module
- Owns storefronts, categories, listings, variants, inventory.
- Tables: `storefronts`, `categories`, `products`, `product_variants`, `inventory_records`.

### Cart and Checkout Module
- Owns pre-order selection and price snapshots.
- Tables: `carts`, `cart_items`.

### Order Module
- Owns order/item lifecycle and transition logging.
- Tables: `orders`, `order_items`, `order_state_transitions`.

### Payments Integration Module
- Owns payment provider intents, transactions, and webhook intake.
- Tables: `payment_intents`, `payment_transactions`, `payment_webhook_events`.

### Escrow Module (Critical)
- Owns funds hold/release/refund/dispute state.
- Tables: `escrow_accounts`, `escrow_events`.

### Wallet and Ledger Module (Critical)
- Owns append-only accounting engine and balances.
- Tables: `wallets`, `wallet_holds`, `wallet_ledger_batches`, `wallet_ledger_entries`, `wallet_balance_snapshots`.

### Withdrawals Module (Critical)
- Owns payout request lifecycle and payout attempts.
- Tables: `withdrawal_requests`, `withdrawal_transactions`, `payout_accounts`.

### Dispute Module (Critical)
- Owns case workflow, evidence, and adjudication.
- Tables: `dispute_cases`, `dispute_evidences`, `dispute_decisions`.

### Membership and Monetization Module
- Owns subscriptions, plan entitlements, and commission policies.
- Tables: `membership_plans`, `membership_subscriptions`, `commission_rules`.

### Reputation Module
- Owns post-purchase feedback.
- Tables: `reviews`.

### Notification Module
- Owns user notification delivery state.
- Tables: `notifications`.

### Audit and Compliance Module
- Owns immutable audit history and idempotency governance.
- Tables: `audit_logs`, `idempotency_keys`, `outbox_events`.

---

## 4) State Machines

### Orders
States:
- `draft`
- `pending_payment`
- `paid`
- `processing`
- `shipped_or_delivered`
- `completed`
- `cancelled`
- `refunded`
- `disputed`

Valid transitions:
- `draft -> pending_payment`
- `pending_payment -> paid | cancelled`
- `paid -> processing`
- `processing -> shipped_or_delivered`
- `shipped_or_delivered -> completed`
- `paid|processing|shipped_or_delivered -> disputed`
- `disputed -> completed | refunded`
- constrained cancel/refund paths based on fulfillment and escrow status

### Escrow
States:
- `initiated`
- `held`
- `under_dispute`
- `released` (terminal)
- `refunded` (terminal)

Valid transitions:
- `initiated -> held`
- `held -> released | refunded | under_dispute`
- `under_dispute -> released | refunded`

### Wallet Ledger Events
Event types:
- `deposit_credit`
- `escrow_hold_debit`
- `escrow_release_credit`
- `platform_fee_credit`
- `refund_credit`
- `withdrawal_hold_debit`
- `withdrawal_settlement_debit`
- `withdrawal_reversal_credit`
- `adjustment_credit`
- `adjustment_debit`

Batch lifecycle:
- `proposed -> posted -> reversed` (optional)

### Withdrawals
States:
- `requested`
- `under_review`
- `approved`
- `processing_payout`
- `paid_out`
- `rejected`
- `failed`
- `cancelled`

Valid transitions:
- `requested -> under_review`
- `under_review -> approved | rejected`
- `approved -> processing_payout`
- `processing_payout -> paid_out | failed`
- `failed -> processing_payout | cancelled`

### Disputes
States:
- `opened`
- `evidence_collection`
- `under_review`
- `escalated`
- `resolved`

Resolution outcomes:
- `buyer_wins`
- `seller_wins`
- `split_decision`

### Memberships
States:
- `inactive`
- `active`
- `expired`
- `cancelled`
- `suspended`

Valid transitions:
- `inactive -> active`
- `active -> expired | cancelled | suspended`
- `suspended -> active | cancelled`
- `expired -> active` (renewal)

---

## 5) Business Rules and Invariants

- Backend is the sole source of truth for all money/state transitions.
- No client-side monetary calculation or state authority.
- Financial records are append-only; correction via reversal entries only.
- Idempotency is mandatory for external and retryable mutation paths.
- Ledger-driven accounting: balances derive from ledger + active holds.
- Escrow conservation rule: terminal escrow must satisfy
  - `held_amount = released_amount + refunded_amount`
- Single terminal escrow outcome:
  - `released XOR refunded`
- Withdrawal gate:
  - `requested_amount <= withdrawable_balance`
- KYC gates seller privileges (listing and/or payout based on policy).
- One active dispute per order scope unless explicit policy says otherwise.
- Snapshot immutability at purchase time:
  - product title/SKU/type/prices/commission context preserved on `order_items`.
- Every critical transition must produce audit records with actor and reason.

---

## 6) High-Risk Areas and Integrity Constraints

- Duplicate financial mutations from retries/webhooks.
- Out-of-order external callbacks causing invalid state regressions.
- Partial failure across payment, escrow, and ledger write paths.
- Concurrency races on wallet balances and withdrawals.
- Unauthorized administrative/manual adjustments.
- Membership entitlement drift after expiration/suspension.
- Multi-seller order allocation correctness for escrow and fees.

Required controls:
- unique idempotency keys + request hash verification
- strict FK and unique constraints
- transition guards aligned to state machines
- transaction boundaries with row-level locking on financial operations
- immutable financial and audit event tables
- periodic reconciliation jobs (gateway vs payments vs ledger vs escrow)

---

## 7) Recommended Implementation Order

1. Domain constants/enums/errors and invariant contracts.
2. Wallet and ledger posting kernel (idempotency + reversals).
3. Escrow engine and escrow-ledger integration.
4. Order orchestration and payment-escrow-order coupling.
5. Dispute workflow and adjudication effects.
6. Withdrawal review/payout/retry flow.
7. Seller trust (KYC) gating logic.
8. Catalog/cart/customer experiences.
9. Memberships and commission policy resolution.
10. Admin moderation, audit views, and compliance workflows.
11. Notifications and observability.
12. Reconciliation, load/security testing, and hardening.

---

## 8) Transaction-Sensitive Domain Areas

Tier A (strict financial atomicity):
- Orders + order item financial snapshot finalization
- Escrow state/event mutations
- Wallet holds, ledger batches, ledger entries
- Withdrawal request and payout transitions
- Payment intent/transaction mutation with idempotency

Tier B (financially coupled):
- Dispute decisions and resulting escrow/ledger effects
- Membership transitions affecting fee policy

Tier C (operational integrity):
- Webhook processing inbox
- Outbox publication queue
- Audit and transition logging


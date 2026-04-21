# Sellova DDL Specification

## Purpose

This document defines the migration-ready database specification for Sellova. It is aligned with:
- approved domain map
- approved state machines
- financial integrity requirements

Primary target: **MySQL 8.0+ (InnoDB, utf8mb4)**.

---

## 1) Ordered Execution Plan

### Phase 0: Session and engine settings
- SQL mode, charset/collation, timezone.

### Phase 1: `CREATE TABLE` statements (no foreign keys)
Create tables in this dependency-safe order:

1. `users`
2. `roles`
3. `permissions`
4. `user_roles`
5. `role_permissions`
6. `seller_profiles`
7. `kyc_verifications`
8. `kyc_documents`
9. `storefronts`
10. `categories`
11. `products`
12. `product_variants`
13. `inventory_records`
14. `carts`
15. `cart_items`
16. `membership_plans`
17. `commission_rules`
18. `orders`
19. `order_items`
20. `order_state_transitions`
21. `idempotency_keys`
22. `payment_intents`
23. `payment_transactions`
24. `payment_webhook_events`
25. `escrow_accounts`
26. `escrow_events`
27. `wallets`
28. `wallet_holds`
29. `wallet_ledger_batches`
30. `wallet_ledger_entries`
31. `wallet_balance_snapshots`
32. `withdrawal_requests`
33. `withdrawal_transactions`
34. `payout_accounts`
35. `membership_subscriptions`
36. `dispute_cases`
37. `dispute_evidences`
38. `dispute_decisions`
39. `reviews`
40. `notifications`
41. `audit_logs`
42. `outbox_events`

### Phase 2: `ALTER TABLE` for foreign keys
- Add all FK constraints after table creation.
- Use `RESTRICT` by default for financial safety.

### Phase 3: Index creation
- Secondary indexes for workflow, reporting, reconciliation, and forensic queries.

### Phase 4: Partitioning/archival rollout
- Apply partitioning strategy to large-volume tables.

---

## 2) Core Typing and Constraints Standards

- Primary keys: `BIGINT UNSIGNED AUTO_INCREMENT`
- External identifiers: `CHAR(36)` UUIDs with unique indexes
- Currency code: `CHAR(3)`
- Monetary fields: `DECIMAL(18,4)`
- Timestamps: `DATETIME(6)`
- JSON payload fields: `JSON`

Constraint principles:
- non-negative money checks
- immutable financial snapshots (order line snapshots)
- strict enum state constraints
- escrow conservation constraint on terminal states
- uniqueness for idempotency and external provider references

---

## 3) Foreign Key Strategy

## 3.1 Default behavior
- `ON UPDATE RESTRICT`
- `ON DELETE RESTRICT` for all core and financial references

## 3.2 Allowed exceptions
- `cart_items -> carts` can use `ON DELETE CASCADE` (transient shopping session data)
- nullable actor references may use `ON DELETE SET NULL` (`reviewed_by`, `actor_user_id`)
- product references in historical order lines may use nullable `SET NULL` while keeping snapshots

## 3.3 Deferrable constraints policy
MySQL does not support deferrable FKs. Equivalent operational strategy:
- create parent records first
- keep select child FKs nullable during same-transaction orchestration when needed
- complete linkage before commit
- enforce cross-table rules in service layer + audit + reconciliation checks

---

## 4) Indexing Strategy

## 4.1 Uniqueness indexes
- UUID unique on all aggregate roots
- business uniques:
  - `orders.order_number`
  - `storefronts.slug`
  - `product_variants.sku`
  - provider refs in `payment_intents`, `payment_transactions`, `payment_webhook_events`
  - `wallets(user_id, wallet_type, currency)`
  - idempotency keys

## 4.2 State and timeline indexes
- `orders(status, created_at, id)`
- `withdrawal_requests(status, created_at, id)`
- `dispute_cases(status, opened_at, id)`
- `membership_subscriptions(seller_profile_id, status, expires_at)`
- webhook and outbox processing queues by status+time

## 4.3 Financial traversal indexes
- `wallet_ledger_entries(wallet_id, occurred_at, id)`
- `wallet_ledger_entries(reference_type, reference_id)`
- `wallet_holds(wallet_id, status, created_at)`
- `escrow_events(escrow_account_id, created_at, id)`
- `wallet_ledger_batches(reference_type, reference_id, status)`

## 4.4 Audit and forensic indexes
- `audit_logs(target_type, target_id, created_at)`
- `audit_logs(actor_user_id, created_at)`
- `audit_logs(correlation_id)`
- `order_state_transitions(order_id, created_at)`
- `order_state_transitions(correlation_id)`

---

## 5) Soft Delete vs Hard Delete

### Soft delete required (`deleted_at`)
- `users`
- `seller_profiles`
- `products`

### Hard delete allowed (non-financial transient)
- `carts`
- `cart_items`

### Hard delete prohibited (append-only or compliance-critical)
- `orders`, `order_items`, `order_state_transitions`
- `payment_intents`, `payment_transactions`, `payment_webhook_events`
- `escrow_accounts`, `escrow_events`
- `wallets`, `wallet_holds`, `wallet_ledger_batches`, `wallet_ledger_entries`, `wallet_balance_snapshots`
- `withdrawal_requests`, `withdrawal_transactions`
- `dispute_cases`, `dispute_evidences`, `dispute_decisions`
- `membership_subscriptions`, `commission_rules` (effective history retention)
- `audit_logs`, `idempotency_keys`, `outbox_events`

---

## 6) Audit and Logging Requirements (DB-Level)

Mandatory append-only event history:
- `order_state_transitions`
- `escrow_events`
- `wallet_ledger_entries`
- `audit_logs`
- `payment_webhook_events`
- `outbox_events`

Mandatory fields for critical actions:
- actor (`actor_user_id`/`reviewed_by`/`decided_by`)
- correlation id
- timestamps
- reason code where applicable
- before/after JSON (for `audit_logs`)

Operational recommendation:
- enforce append-only patterns at service layer, optionally via database triggers that prevent update/delete on financial event tables.

---

## 7) Transaction-Sensitive Tables

### Tier A (strict financial atomicity)
- `orders`, `order_items`
- `payment_intents`, `payment_transactions`
- `escrow_accounts`, `escrow_events`
- `wallets`, `wallet_holds`, `wallet_ledger_batches`, `wallet_ledger_entries`
- `withdrawal_requests`, `withdrawal_transactions`
- `idempotency_keys`

### Tier B (financially coupled)
- `dispute_cases`, `dispute_decisions`
- `membership_subscriptions`
- `commission_rules`

### Tier C (operational integrity)
- `payment_webhook_events`
- `outbox_events`
- `audit_logs`
- `order_state_transitions`

Transactional controls:
- row locking (`SELECT ... FOR UPDATE`) on wallet/escrow/order mutation paths
- idempotent processing keys required for external retries/webhooks

---

## 8) Large-Table Partitioning and Archival

Partition/archive targets:
- `wallet_ledger_entries` by `occurred_at`
- `audit_logs` by `created_at`
- `payment_webhook_events` by `received_at`
- `outbox_events` by `created_at`
- optional: `orders`, `order_state_transitions`

Recommended policy:
- keep 12-18 months in hot transactional storage
- archive older immutable partitions to cold storage/archive schema
- preserve key references and UUID traceability across archive boundaries

---

## 9) Referential Integrity and State-Machine Alignment

- Enums match approved state machines for:
  - orders
  - escrow
  - withdrawals
  - disputes
  - memberships
  - wallet ledger events
- FK graph mirrors domain map exactly.
- High-risk invariants are enforced through:
  - DB constraints (non-negative amounts, uniqueness, snapshots)
  - transactional application orchestration
  - immutable event logs
  - reconciliation jobs


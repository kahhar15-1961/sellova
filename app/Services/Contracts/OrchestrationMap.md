# Service-Layer Contracts Map

## State transitions
- Enforced inside domain service methods before writes.

## Invariants
- Validated in service preconditions and before commit.

## Idempotency
- Handled at service entry points for retryable external commands.

## Transaction boundaries
- Required for all financial and coupled state changes:
  - Order
  - Escrow
  - Wallet/Ledger
  - Withdrawal
  - Dispute
  - Membership (payment/fee impact)

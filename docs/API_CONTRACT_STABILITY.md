# API Contract Stability

This document defines contract-stable HTTP endpoints, envelope formats, and out-of-scope modules.

## Stable Base Contract

- Base path: `/api/v1`
- Content type: `application/json`
- Success envelope:
  - `{ "data": <object|array>, "meta"?: <object> }`
- Error envelope:
  - `{ "error": <string>, "message": <string>, ...context }`

### Paginated `meta` contract

All list endpoints return:

- `meta.page` (int)
- `meta.per_page` (int)
- `meta.total` (int)
- `meta.last_page` (int)

## Contract-Stable Live Endpoints

### Auth

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/refresh`
- `POST /auth/logout`

### Actor profile

- `GET /me`
- `PATCH /me`
- `GET /me/seller`
- `PATCH /me/seller`

### Products (public catalog)

- `GET /products`
- `GET /products/search`
- `GET /products/{productId}`

### Orders

- `GET /orders`
- `GET /orders/{orderId}`
- `POST /orders/{orderId}/mark-pending-payment`
- `POST /orders/{orderId}/mark-paid`

### Disputes

- `GET /disputes`
- `GET /disputes/{disputeCaseId}`
- `POST /orders/{orderId}/disputes`
- `POST /disputes/{disputeCaseId}/evidence`
- `POST /disputes/{disputeCaseId}/move-to-review`
- `POST /disputes/{disputeCaseId}/escalate`
- `POST /disputes/{disputeCaseId}/resolve/refund`
- `POST /disputes/{disputeCaseId}/resolve/release`
- `POST /disputes/{disputeCaseId}/resolve/split`

### Withdrawals

- `GET /withdrawals`
- `GET /withdrawals/{withdrawalRequestId}`
- `POST /withdrawals`
- `POST /withdrawals/{withdrawalRequestId}/review`
- `POST /withdrawals/{withdrawalRequestId}/approve`
- `POST /withdrawals/{withdrawalRequestId}/reject`

## Error Contract Categories

Contract-stable error classes:

- Validation: `validation_failed` (HTTP 422)
- Authorization: `forbidden` (HTTP 403)
- Authentication: `unauthenticated` (HTTP 401)
- Not found: `not_found` (HTTP 404)
- Conflict / idempotency:
  - `conflict` (HTTP 409)
  - `idempotency_conflict` (HTTP 409)
- Domain validation:
  - `validation_failed` (HTTP 422) with contextual fields
  - `invalid_state_transition` (HTTP 409)
- Generic internal: `internal_error` (HTTP 500)

## Out of Scope / Not Contract-Stable Yet

Intentionally excluded from frontend hardening right now:

- Product create/update/delete flows
- Payout pipeline transitions:
  - `/withdrawals/{id}/payout/submit`
  - `/withdrawals/{id}/payout/confirm`
  - `/withdrawals/{id}/payout/fail`
- Order create and fulfillment progression APIs
- Any endpoint currently returning `not_implemented`

## Verification

Contract shape assertions are codified in:

- `tests/Http/ApiV1ContractTest.php`
- `tests/Http/ApiV1IntegrationTest.php`


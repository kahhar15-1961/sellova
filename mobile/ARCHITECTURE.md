# Sellova Mobile Architecture

This app scaffold uses:

- Riverpod for state and dependency wiring
- Dio-based API/data layer (`lib/core/network` + `lib/features/*/data`)
- Feature-first structure with separation between presentation, application/state, and data

## Layers

- `presentation`: widgets, screens, and navigation-aware UI composition
- `application`: Riverpod notifiers/controllers and state orchestration
- `data`: repositories and DTOs speaking to backend contract

## Auth/session lifecycle

1. Bootstrap creates `PersistentTokenStore`.
2. Splash triggers `AuthSessionController.restore()`.
3. If refresh token exists, restore via `/api/v1/auth/refresh`.
4. Router redirects by auth state:
   - `unknown` -> `/splash`
   - `unauthenticated` -> `/sign-in`
   - `authenticated` -> `/home`
5. Auth interceptor retries one failed `401` request after refresh.

## Global async/error strategy

- `globalLoadingProvider` controls full-screen loading overlay.
- `globalErrorProvider` drives top-level SnackBar messaging.
- `runAsyncAction` helper standardizes button-level async state.

## Pagination strategy

- `PaginatedState<T>` keeps `items`, `meta`, loading and append flags.
- Feature controllers load first page, then append using `meta.hasMore`.

<?php

declare(strict_types=1);

namespace App\Services\WalletTopUp;

use App\Domain\Commands\WalletTopUp\RequestWalletTopUpCommand;
use App\Domain\Commands\WalletTopUp\ReviewWalletTopUpCommand;
use App\Domain\Enums\WalletTopUpRequestStatus;
use App\Domain\Enums\WalletTopUpReviewDecision;
use App\Domain\Enums\WalletType;
use App\Auth\RoleCodes;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidDomainStateTransitionException;
use App\Domain\Exceptions\InvalidLedgerOperationException;
use App\Domain\Exceptions\WalletCurrencyMismatchException;
use App\Domain\Exceptions\WalletNotFoundException;
use App\Models\IdempotencyKey;
use App\Models\Notification;
use App\Models\Wallet;
use App\Models\WalletLedgerBatch;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTopUpRequest;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayService;
use App\Services\PushNotification\PushNotificationService;
use App\Services\Support\FinancialCritical;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WalletTopUpRequestService
{
    use FinancialCritical;

    public function __construct(
        private readonly WalletLedgerService $walletLedgerService = new WalletLedgerService(),
        private readonly PaymentGatewayService $paymentGatewayService = new PaymentGatewayService(),
    ) {}

    public function requestTopUp(RequestWalletTopUpCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $wallet = Wallet::query()->whereKey($command->walletId)->lockForUpdate()->first();
            if ($wallet === null) {
                throw new WalletNotFoundException($command->walletId);
            }
            if ((int) $wallet->user_id !== (int) $command->userId) {
                throw new InvalidLedgerOperationException('wallet_top_up_wallet_owner_mismatch');
            }
            if ($wallet->wallet_type !== WalletType::Buyer) {
                throw new InvalidLedgerOperationException('wallet_top_up_not_allowed');
            }

            $amountScale = $this->toScale($command->amount);
            if ($amountScale <= 0) {
                throw new InvalidLedgerOperationException('top_up_amount_must_be_positive');
            }
            $paymentMethod = trim(strtolower($command->paymentMethod));
            if (! $this->isAllowedTopUpMethod($paymentMethod)) {
                throw new InvalidLedgerOperationException('wallet_top_up_payment_method_invalid');
            }
            $paymentReference = trim((string) ($command->paymentReference ?? ''));
            if ($paymentReference === '') {
                throw new InvalidLedgerOperationException('wallet_top_up_payment_reference_required');
            }

            $requestHash = hash('sha256', json_encode([
                'wallet_id' => $command->walletId,
                'user_id' => $command->userId,
                'amount' => $this->fromScale($amountScale),
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
            ], JSON_THROW_ON_ERROR));

            $idem = IdempotencyKey::query()
                ->where('scope', 'wallet_top_up_request_submit')
                ->where('key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($idem !== null) {
                if ($idem->request_hash !== $requestHash) {
                    throw new IdempotencyConflictException($command->idempotencyKey, 'wallet_top_up_request_submit');
                }

                $existing = WalletTopUpRequest::query()->where('idempotency_key', $command->idempotencyKey)->first();
                if ($existing !== null) {
                    return $this->requestPayload($existing, true);
                }

                throw new IdempotencyConflictException($command->idempotencyKey, 'wallet_top_up_request_submit');
            }

            try {
                $idem = IdempotencyKey::query()->create([
                    'key' => $command->idempotencyKey,
                    'scope' => 'wallet_top_up_request_submit',
                    'request_hash' => $requestHash,
                    'status' => \App\Domain\Enums\IdempotencyKeyStatus::Started,
                    'expires_at' => now()->addDay(),
                ]);
            } catch (QueryException $e) {
                $existing = WalletTopUpRequest::query()->where('idempotency_key', $command->idempotencyKey)->first();
                if ($existing !== null) {
                    return $this->requestPayload($existing, true);
                }

                throw new IdempotencyConflictException($command->idempotencyKey, 'wallet_top_up_request_submit', previous: $e);
            }

            try {
                $request = WalletTopUpRequest::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'idempotency_key' => $command->idempotencyKey,
                    'wallet_id' => $wallet->id,
                    'requested_by_user_id' => $command->userId,
                    'status' => WalletTopUpRequestStatus::Requested,
                    'requested_amount' => $this->fromScale($amountScale),
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                    'currency' => $wallet->currency,
                ]);
            } catch (QueryException $e) {
                $existing = WalletTopUpRequest::query()->where('idempotency_key', $command->idempotencyKey)->first();
                if ($existing !== null) {
                    return $this->requestPayload($existing, true);
                }

                throw new InvalidLedgerOperationException('wallet_top_up_request_create_failed', previous: $e);
            }

            $this->markIdempotencySucceeded($idem, [
                'wallet_top_up_request_id' => $request->id,
                'status' => $request->status->value,
            ]);

            $this->notifyUser(
                userId: (int) $command->userId,
                templateCode: 'wallet.top_up.requested',
                payload: [
                    'title' => 'Top-up requested',
                    'body' => 'Your wallet top-up request is waiting for review.',
                    'href' => '/profile/wallet',
                    'wallet_top_up_request_id' => $request->id,
                    'wallet_id' => $wallet->id,
                    'amount' => (string) $request->requested_amount,
                    'status' => $request->status->value,
                ],
            );
            $this->notifyStaff(
                templateCode: 'admin.wallet_top_up.requested',
                payload: [
                    'title' => 'Wallet top-up pending',
                    'body' => 'A buyer submitted a wallet funding request.',
                    'href' => route('admin.wallet-top-ups.show', $request),
                    'wallet_top_up_request_id' => $request->id,
                    'wallet_id' => $wallet->id,
                    'requested_by_user_id' => $command->userId,
                    'amount' => (string) $request->requested_amount,
                    'payment_method' => $paymentMethod,
                ],
            );

            return $this->requestPayload($request, false);
        });
    }

    /**
     * @return list<string>
     */
    private function allowedTopUpMethods(): array
    {
        $methods = [];
        $manualAllowed = false;

        foreach ($this->paymentGatewayService->enabled() as $gateway) {
            if ($gateway->walletManualTopUpEnabled()) {
                $manualAllowed = true;
            }
            foreach ($gateway->supported_methods ?? [] as $method) {
                $method = strtolower(trim((string) $method));
                if ($method !== '' && in_array($method, ['card', 'bkash', 'nagad', 'bank'], true)) {
                    $methods[] = $method;
                }
            }
        }

        $methods = array_values(array_unique($methods));
        if ($manualAllowed) {
            $methods[] = 'manual';
        }

        return array_values(array_unique($methods));
    }

    private function isAllowedTopUpMethod(string $paymentMethod): bool
    {
        $paymentMethod = strtolower(trim($paymentMethod));
        if ($paymentMethod === '') {
            return false;
        }

        return in_array($paymentMethod, $this->allowedTopUpMethods(), true);
    }

    public function reviewTopUp(ReviewWalletTopUpCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $request = WalletTopUpRequest::query()->whereKey($command->walletTopUpRequestId)->lockForUpdate()->first();
            if ($request === null) {
                throw new InvalidLedgerOperationException('wallet_top_up_request_not_found');
            }

            $requestHash = hash('sha256', json_encode([
                'wallet_top_up_request_id' => $command->walletTopUpRequestId,
                'reviewer_user_id' => $command->reviewerUserId,
                'decision' => $command->decision->value,
                'reason' => $command->reason,
            ], JSON_THROW_ON_ERROR));

            $idem = IdempotencyKey::query()
                ->where('scope', 'wallet_top_up_request_review')
                ->where('key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($idem !== null) {
                if ($idem->request_hash !== $requestHash) {
                    throw new IdempotencyConflictException($command->idempotencyKey, 'wallet_top_up_request_review');
                }

                if ($request->status === WalletTopUpRequestStatus::Approved) {
                    $this->ensureApprovedTopUpCredited($request);
                }

                return $this->reviewPayload($request, true);
            }

            try {
                $idem = IdempotencyKey::query()->create([
                    'key' => $command->idempotencyKey,
                    'scope' => 'wallet_top_up_request_review',
                    'request_hash' => $requestHash,
                    'status' => \App\Domain\Enums\IdempotencyKeyStatus::Started,
                    'expires_at' => now()->addDay(),
                ]);
            } catch (QueryException) {
                $latest = WalletTopUpRequest::query()->whereKey($command->walletTopUpRequestId)->firstOrFail();

                return $this->reviewPayload($latest, true);
            }

            $out = match ($command->decision) {
                WalletTopUpReviewDecision::Approved => $this->approveTopUp($request, $command->reviewerUserId),
                WalletTopUpReviewDecision::Rejected => $this->rejectTopUp($request, $command->reviewerUserId, $command->reason ?? ''),
            };

            $this->markIdempotencySucceeded($idem, $out);

            return $out + ['idempotent_replay' => false];
        });
    }

    private function approveTopUp(WalletTopUpRequest $request, int $reviewerUserId): array
    {
        if ($request->status === WalletTopUpRequestStatus::Approved) {
            $this->ensureApprovedTopUpCredited($request);
            return $this->reviewPayload($request, true);
        }

        if ($request->status !== WalletTopUpRequestStatus::Requested) {
            throw new InvalidDomainStateTransitionException(
                aggregate: 'wallet_top_up_request',
                aggregateId: $request->id,
                fromState: $request->status->value,
                toState: WalletTopUpRequestStatus::Approved->value,
            );
        }

        $wallet = Wallet::query()->whereKey($request->wallet_id)->lockForUpdate()->first();
        if ($wallet === null) {
            throw new WalletNotFoundException($request->wallet_id);
        }
        if ((string) $wallet->currency !== (string) $request->currency) {
            throw new WalletCurrencyMismatchException(
                walletId: $wallet->id,
                walletCurrency: (string) $wallet->currency,
                requestedCurrency: (string) $request->currency,
            );
        }

        $batch = $this->walletLedgerService->topUpWallet(
            walletId: $wallet->id,
            amount: (string) $request->requested_amount,
            idempotencyKey: 'wallet-top-up-request:'.$request->id,
            description: 'wallet_top_up_request',
        );

        $request->status = WalletTopUpRequestStatus::Approved;
        $request->reviewed_by_user_id = $reviewerUserId;
        $request->reviewed_at = now();
        $request->ledger_batch_id = (int) $batch['batch_id'];
        $request->save();

        $this->notifyUser(
            userId: (int) $request->requested_by_user_id,
            templateCode: 'wallet.top_up.approved',
            payload: [
                'title' => 'Top-up approved',
                'body' => 'Your wallet has been credited successfully.',
                'href' => '/profile/wallet',
                'wallet_top_up_request_id' => $request->id,
                'wallet_id' => $wallet->id,
                'amount' => (string) $request->requested_amount,
                'status' => $request->status->value,
            ],
        );
        $this->notifyStaff(
            templateCode: 'admin.wallet_top_up.approved',
            payload: [
                'title' => 'Wallet top-up approved',
                'body' => 'A wallet funding request was approved and credited.',
                'href' => route('admin.wallet-top-ups.show', $request),
                'wallet_top_up_request_id' => $request->id,
                'wallet_id' => $wallet->id,
                'reviewed_by_user_id' => $reviewerUserId,
                'amount' => (string) $request->requested_amount,
            ],
        );

        return [
            'wallet_top_up_request_id' => $request->id,
            'status' => $request->status->value,
            'batch_id' => (int) $batch['batch_id'],
        ];
    }

    /**
     * Repairs approved requests that lost their immutable ledger batch because of a bad
     * local reset or partial historical data import. Normal approvals are no-ops here.
     */
    private function ensureApprovedTopUpCredited(WalletTopUpRequest $request): void
    {
        if ($request->status !== WalletTopUpRequestStatus::Approved) {
            return;
        }

        if ($request->ledger_batch_id !== null && $this->topUpBatchHasCredit($request)) {
            return;
        }

        if ($request->ledger_batch_id !== null && WalletLedgerBatch::query()->whereKey($request->ledger_batch_id)->exists()) {
            throw new InvalidLedgerOperationException('approved_top_up_batch_missing_credit_entry');
        }

        $wallet = Wallet::query()->whereKey($request->wallet_id)->lockForUpdate()->first();
        if ($wallet === null) {
            throw new WalletNotFoundException($request->wallet_id);
        }
        if ((string) $wallet->currency !== (string) $request->currency) {
            throw new WalletCurrencyMismatchException(
                walletId: $wallet->id,
                walletCurrency: (string) $wallet->currency,
                requestedCurrency: (string) $request->currency,
            );
        }

        $batch = $this->walletLedgerService->topUpWallet(
            walletId: $wallet->id,
            amount: (string) $request->requested_amount,
            idempotencyKey: 'wallet-top-up-request:'.$request->id,
            description: 'wallet_top_up_request_repair',
        );

        $request->ledger_batch_id = (int) $batch['batch_id'];
        $request->save();
        $request->refresh();
    }

    private function topUpBatchHasCredit(WalletTopUpRequest $request): bool
    {
        return WalletLedgerEntry::query()
            ->where('batch_id', $request->ledger_batch_id)
            ->where('wallet_id', $request->wallet_id)
            ->where('entry_side', \App\Domain\Enums\WalletLedgerEntrySide::Credit->value)
            ->where('entry_type', \App\Domain\Enums\WalletLedgerEntryType::DepositCredit->value)
            ->where('amount', (string) $request->requested_amount)
            ->exists();
    }

    /**
     * @return array{checked: int, repaired: int, skipped_orphaned: int}
     */
    public function reconcileApprovedCredits(int $limit = 500): array
    {
        $checked = 0;
        $repaired = 0;
        $skippedOrphaned = 0;

        WalletTopUpRequest::query()
            ->where('status', WalletTopUpRequestStatus::Approved->value)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (WalletTopUpRequest $request) use (&$checked, &$repaired, &$skippedOrphaned): void {
                DB::transaction(function () use ($request, &$checked, &$repaired, &$skippedOrphaned): void {
                    $locked = WalletTopUpRequest::query()->whereKey($request->id)->lockForUpdate()->first();
                    if ($locked === null) {
                        return;
                    }

                    $checked++;
                    if (! Wallet::query()->whereKey($locked->wallet_id)->exists()) {
                        $skippedOrphaned++;
                        return;
                    }

                    $wasCredited = $locked->ledger_batch_id !== null && $this->topUpBatchHasCredit($locked);
                    $this->ensureApprovedTopUpCredited($locked);
                    $fresh = $locked->fresh();
                    if (! $wasCredited && $fresh !== null && $fresh->ledger_batch_id !== null && $this->topUpBatchHasCredit($fresh)) {
                        $repaired++;
                    }
                });
            });

        return ['checked' => $checked, 'repaired' => $repaired, 'skipped_orphaned' => $skippedOrphaned];
    }

    private function rejectTopUp(WalletTopUpRequest $request, int $reviewerUserId, string $reason): array
    {
        if ($request->status === WalletTopUpRequestStatus::Rejected) {
            return $this->reviewPayload($request, true);
        }

        if ($request->status !== WalletTopUpRequestStatus::Requested) {
            throw new InvalidDomainStateTransitionException(
                aggregate: 'wallet_top_up_request',
                aggregateId: $request->id,
                fromState: $request->status->value,
                toState: WalletTopUpRequestStatus::Rejected->value,
            );
        }

        $wallet = Wallet::query()->whereKey($request->wallet_id)->lockForUpdate()->first();
        if ($wallet === null) {
            throw new WalletNotFoundException($request->wallet_id);
        }

        $request->status = WalletTopUpRequestStatus::Rejected;
        $request->reviewed_by_user_id = $reviewerUserId;
        $request->reviewed_at = now();
        $request->rejection_reason = trim($reason) !== '' ? trim($reason) : null;
        $request->save();

        $this->notifyUser(
            userId: (int) $request->requested_by_user_id,
            templateCode: 'wallet.top_up.rejected',
            payload: [
                'title' => 'Top-up rejected',
                'body' => 'Your wallet funding request was not approved.',
                'href' => '/profile/wallet',
                'wallet_top_up_request_id' => $request->id,
                'wallet_id' => $wallet->id,
                'amount' => (string) $request->requested_amount,
                'status' => $request->status->value,
                'reason' => $request->rejection_reason,
            ],
        );
        $this->notifyStaff(
            templateCode: 'admin.wallet_top_up.rejected',
            payload: [
                'title' => 'Wallet top-up rejected',
                'body' => 'A wallet funding request was rejected.',
                'href' => route('admin.wallet-top-ups.show', $request),
                'wallet_top_up_request_id' => $request->id,
                'wallet_id' => $wallet->id,
                'reviewed_by_user_id' => $reviewerUserId,
                'amount' => (string) $request->requested_amount,
                'reason' => $request->rejection_reason,
            ],
        );

        return [
            'wallet_top_up_request_id' => $request->id,
            'status' => $request->status->value,
        ];
    }

    private function requestPayload(WalletTopUpRequest $request, bool $idempotentReplay): array
    {
        return [
            'wallet_top_up_request_id' => $request->id,
            'status' => $request->status->value,
            'requested_amount' => (string) $request->requested_amount,
            'payment_method' => (string) ($request->payment_method ?? ''),
            'payment_reference' => (string) ($request->payment_reference ?? ''),
            'currency' => (string) ($request->currency ?? ''),
            'idempotent_replay' => $idempotentReplay,
        ];
    }

    private function reviewPayload(WalletTopUpRequest $request, bool $idempotentReplay): array
    {
        $payload = [
            'wallet_top_up_request_id' => $request->id,
            'status' => $request->status->value,
            'idempotent_replay' => $idempotentReplay,
        ];

        if ($request->ledger_batch_id !== null) {
            $payload['batch_id'] = (int) $request->ledger_batch_id;
        }

        return $payload;
    }

    private function markIdempotencySucceeded(IdempotencyKey $idempotency, array $payload): void
    {
        $idempotency->status = \App\Domain\Enums\IdempotencyKeyStatus::Succeeded;
        $idempotency->response_hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $idempotency->save();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notifyUser(int $userId, string $templateCode, array $payload): void
    {
        $notification = Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'channel' => 'in_app',
            'template_code' => $templateCode,
            'payload_json' => $payload,
            'status' => 'queued',
            'sent_at' => now(),
        ]);

        \App\Events\UserNotificationCreated::dispatch(
            $userId,
            [
                'id' => (int) $notification->id,
                'uuid' => (string) $notification->uuid,
                'channel' => (string) $notification->channel,
                'template_code' => $templateCode,
                'title' => (string) ($payload['title'] ?? $templateCode),
                'body' => (string) ($payload['body'] ?? ''),
                'href' => (string) ($payload['href'] ?? ''),
                'payload' => $payload,
                'is_read' => false,
                'created_at' => $notification->created_at?->toIso8601String(),
            ],
            Notification::query()->where('user_id', $userId)->whereNull('read_at')->count(),
        );

        app(PushNotificationService::class)->sendToUser($userId, [
            'title' => (string) ($payload['title'] ?? $templateCode),
            'body' => (string) ($payload['body'] ?? ''),
            'template_code' => $templateCode,
            'wallet_top_up_request_id' => $payload['wallet_top_up_request_id'] ?? null,
            'wallet_id' => $payload['wallet_id'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'status' => $payload['status'] ?? null,
            'reason' => $payload['reason'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notifyStaff(string $templateCode, array $payload): void
    {
        $staffIds = User::query()
            ->whereHas('roles', static function ($q): void {
                $q->whereIn('roles.code', [
                    RoleCodes::SuperAdmin,
                    RoleCodes::Admin,
                    RoleCodes::FinanceAdmin,
                    RoleCodes::Adjudicator,
                ]);
            })
            ->pluck('id')
            ->all();

        foreach ($staffIds as $staffId) {
            $this->notifyUser((int) $staffId, $templateCode, $payload);
        }
    }

    private function toScale(string $amount): int
    {
        return (int) round(((float) $amount) * 10000);
    }

    private function fromScale(int $scale): string
    {
        return number_format($scale / 10000, 4, '.', '');
    }
}

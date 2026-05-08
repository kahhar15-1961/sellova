<?php

namespace App\Services\Withdrawal;

use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Domain\Commands\WalletLedger\PlaceWalletHoldCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Commands\WalletLedger\ReleaseWalletHoldCommand;
use App\Domain\Commands\Withdrawal\ApproveWithdrawalCommand;
use App\Domain\Commands\Withdrawal\ConfirmPayoutCommand;
use App\Domain\Commands\Withdrawal\FailPayoutCommand;
use App\Domain\Commands\Withdrawal\RejectWithdrawalCommand;
use App\Domain\Commands\Withdrawal\RequestWithdrawalCommand;
use App\Domain\Commands\Withdrawal\ReviewWithdrawalCommand;
use App\Domain\Commands\Withdrawal\SubmitPayoutCommand;
use App\Domain\Queries\Withdrawals\WithdrawalListQuery;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\WalletHoldType;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Enums\WithdrawalRequestStatus;
use App\Domain\Enums\WithdrawalReviewDecision;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Domain\Exceptions\InvalidDomainStateTransitionException;
use App\Domain\Exceptions\InvalidLedgerOperationException;
use App\Domain\Exceptions\WithdrawalValidationFailedException;
use App\Domain\Value\LedgerPostingLine;
use App\Models\IdempotencyKey;
use App\Models\SellerProfile;
use App\Models\Wallet;
use App\Models\WalletLedgerBatch;
use App\Models\WithdrawalRequest;
use App\Services\Support\FinancialCritical;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WithdrawalService
{
    use FinancialCritical;

    public function __construct(
        private readonly WalletLedgerService $walletLedgerService = new WalletLedgerService(),
        private readonly WithdrawalSettingsService $settingsService = new WithdrawalSettingsService(),
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function listWithdrawalRequests(WithdrawalListQuery $query): array
    {
        $builder = WithdrawalRequest::query()->with(['seller_profile'])->orderByDesc('id');
        if (! $query->viewerIsPlatformStaff) {
            $builder->whereHas('seller_profile', static function ($sp) use ($query): void {
                $sp->where('user_id', $query->viewerUserId);
            });
        }

        $page = max(1, $query->page);
        $perPage = min(100, max(1, $query->perPage));
        $total = (int) $builder->count();
        $rows = (clone $builder)->forPage($page, $perPage)->get();
        $items = [];
        foreach ($rows as $wr) {
            $items[] = [
                'id' => $wr->id,
                'uuid' => $wr->uuid,
                'seller_profile_id' => $wr->seller_profile_id,
                'wallet_id' => $wr->wallet_id,
                'status' => $wr->status->value,
                'requested_amount' => (string) $wr->requested_amount,
                'currency' => $wr->currency,
                'created_at' => $wr->created_at?->toIso8601String(),
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

    public function requestWithdrawal(RequestWithdrawalCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $requestHash = $this->hashPayload([
                'seller_profile_id' => $command->sellerProfileId,
                'wallet_id' => $command->walletId,
                'amount' => $command->amount,
                'currency' => $command->currency,
                'fee_amount' => $command->feeAmount,
            ]);
            $idem = $this->claimIdempotency('withdrawal_request_submit', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return $this->buildRequestWithdrawalReplayPayload($command->idempotencyKey, $command->sellerProfileId);
            }

            $wallet = Wallet::query()->whereKey($command->walletId)->lockForUpdate()->first();
            if ($wallet === null) {
                throw new WithdrawalValidationFailedException(null, 'wallet_not_found', ['wallet_id' => $command->walletId]);
            }

            $this->assertSellerOwnsWallet($command->sellerProfileId, $wallet);
            $this->assertWalletCurrency($wallet, $command->currency);

            $requestedScale = $this->toScale($command->amount);
            if ($requestedScale <= 0) {
                throw new WithdrawalValidationFailedException(null, 'withdrawal_amount_must_be_positive', []);
            }

            $feeStr = $command->feeAmount ?? '0.0000';
            $feeScale = $this->toScale($feeStr);
            if ($feeScale < 0) {
                throw new WithdrawalValidationFailedException(null, 'withdrawal_fee_invalid', ['fee_amount' => $feeStr]);
            }
            if ($feeScale > 0) {
                throw new WithdrawalValidationFailedException(null, 'withdrawal_nonzero_fee_not_supported', [
                    'fee_amount' => $feeStr,
                ]);
            }

            $minimumScale = $this->toScale($this->settingsService->minimumAmount());
            if ($requestedScale < $minimumScale) {
                throw new WithdrawalValidationFailedException(
                    null,
                    'withdrawal_amount_below_minimum',
                    [
                        'minimum_withdrawal_amount' => $this->fromScale($minimumScale),
                        'requested_amount' => $command->amount,
                    ],
                    'Withdrawal amount is below the platform minimum.',
                );
            }

            $netScale = $requestedScale - $feeScale;
            if ($netScale <= 0) {
                throw new WithdrawalValidationFailedException(null, 'withdrawal_net_amount_non_positive', []);
            }

            $balances = $this->walletLedgerService->computeWalletBalances(new ComputeWalletBalancesCommand($wallet->id));
            $availableScale = $this->toScale((string) $balances['available_balance']);
            if ($availableScale < $requestedScale) {
                throw new WithdrawalValidationFailedException(
                    null,
                    'insufficient_available_balance_for_withdrawal',
                    [
                        'wallet_id' => $wallet->id,
                        'available_balance' => (string) $balances['available_balance'],
                        'requested_amount' => $command->amount,
                    ],
                );
            }

            $wr = WithdrawalRequest::query()->create([
                'uuid' => (string) Str::uuid(),
                'idempotency_key' => $command->idempotencyKey,
                'seller_profile_id' => $command->sellerProfileId,
                'wallet_id' => $wallet->id,
                'status' => WithdrawalRequestStatus::Requested,
                'requested_amount' => $this->fromScale($requestedScale),
                'fee_amount' => $this->fromScale($feeScale),
                'net_payout_amount' => $this->fromScale($netScale),
                'currency' => $command->currency,
                'hold_id' => null,
            ]);

            try {
                $hold = $this->walletLedgerService->placeHold(new PlaceWalletHoldCommand(
                    walletId: $wallet->id,
                    holdType: WalletHoldType::Withdrawal,
                    referenceType: 'withdrawal_request',
                    referenceId: $wr->id,
                    amount: $command->amount,
                ));
            } catch (InsufficientWalletBalanceException $e) {
                throw new WithdrawalValidationFailedException(
                    $wr->id,
                    'insufficient_available_balance_for_withdrawal_hold',
                    [
                        'wallet_id' => $e->walletId,
                        'requested_amount' => $e->requestedAmount,
                        'available_amount' => $e->availableAmount,
                    ],
                    previous: $e,
                );
            }

            $wr->hold_id = (int) $hold['wallet_hold_id'];
            $wr->save();

            $this->markIdempotencySucceeded($idem['idempotency'], [
                'withdrawal_request_id' => $wr->id,
                'status' => $wr->status->value,
            ]);

            return [
                'withdrawal_request_id' => $wr->id,
                'status' => $wr->status->value,
                'requested_amount' => (string) $wr->requested_amount,
                'fee_amount' => (string) $wr->fee_amount,
                'net_payout_amount' => (string) $wr->net_payout_amount,
                'idempotent_replay' => false,
            ];
        });
    }


    public function approveWithdrawal(ApproveWithdrawalCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $requestHash = $this->hashPayload([
                'withdrawal_request_id' => $command->withdrawalRequestId,
                'reviewer_user_id' => $command->reviewerUserId,
            ]);
            $idem = $this->claimIdempotency('withdrawal_request_approve', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return $this->buildApproveReplayPayload($command->withdrawalRequestId);
            }

            $out = $this->executeApproveSettlement($command->withdrawalRequestId, $command->reviewerUserId);
            $this->markIdempotencySucceeded($idem['idempotency'], $out);

            return $out + ['idempotent_replay' => false];
        });
    }

    public function rejectWithdrawal(RejectWithdrawalCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $requestHash = $this->hashPayload([
                'withdrawal_request_id' => $command->withdrawalRequestId,
                'reviewer_user_id' => $command->reviewerUserId,
                'reason' => $command->reason,
            ]);
            $idem = $this->claimIdempotency('withdrawal_request_reject', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return $this->buildRejectReplayPayload($command->withdrawalRequestId);
            }

            $out = $this->executeRejectWithdrawal($command->withdrawalRequestId, $command->reviewerUserId, $command->reason);
            $this->markIdempotencySucceeded($idem['idempotency'], $out);

            return $out + ['idempotent_replay' => false];
        });
    }

    public function reviewWithdrawal(ReviewWithdrawalCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $requestHash = $this->hashPayload([
                'withdrawal_request_id' => $command->withdrawalRequestId,
                'reviewer_id' => $command->reviewerId,
                'decision' => $command->decision->value,
                'reason' => $command->reason,
            ]);
            $idem = $this->claimIdempotency('withdrawal_request_review', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return $this->buildReviewReplayPayload($command->withdrawalRequestId);
            }

            $out = match ($command->decision) {
                WithdrawalReviewDecision::Approved => $this->executeApproveSettlement($command->withdrawalRequestId, $command->reviewerId),
                WithdrawalReviewDecision::Rejected => $this->executeRejectWithdrawal(
                    $command->withdrawalRequestId,
                    $command->reviewerId,
                    $command->reason ?? '',
                ),
            };

            $this->markIdempotencySucceeded($idem['idempotency'], $out);

            return $out + ['idempotent_replay' => false];
        });
    }

    public function submitPayout(SubmitPayoutCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function confirmPayout(ConfirmPayoutCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function failPayout(FailPayoutCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * @return array{withdrawal_request_id: int, status: string, batch_id?: int}
     */
    private function executeApproveSettlement(int $withdrawalRequestId, int $reviewerUserId): array
    {
        $wr = WithdrawalRequest::query()->whereKey($withdrawalRequestId)->lockForUpdate()->first();
        if ($wr === null) {
            throw new WithdrawalValidationFailedException(null, 'withdrawal_request_not_found', [
                'withdrawal_request_id' => $withdrawalRequestId,
            ]);
        }

        if ($wr->status === WithdrawalRequestStatus::PaidOut) {
            throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_already_paid_out', []);
        }

        if ($wr->status !== WithdrawalRequestStatus::Requested) {
            throw new InvalidDomainStateTransitionException(
                aggregate: 'withdrawal_request',
                aggregateId: $wr->id,
                fromState: $wr->status->value,
                toState: WithdrawalRequestStatus::PaidOut->value,
            );
        }

        if ($wr->hold_id === null) {
            throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_hold_missing', []);
        }

        if ((string) $wr->fee_amount !== '0.0000') {
            throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_nonzero_fee_not_supported', [
                'fee_amount' => (string) $wr->fee_amount,
            ]);
        }

        Wallet::query()->whereKey($wr->wallet_id)->lockForUpdate()->firstOrFail();

        $this->walletLedgerService->releaseHold(new ReleaseWalletHoldCommand((int) $wr->hold_id));

        $settlementAmount = (string) $wr->net_payout_amount;
        $ledger = $this->walletLedgerService->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Withdrawal,
            referenceType: 'withdrawal_request',
            referenceId: $wr->id,
            idempotencyKey: 'withdrawal-settlement:wr:'.$wr->id,
            entries: [
                new LedgerPostingLine(
                    walletId: (int) $wr->wallet_id,
                    entrySide: WalletLedgerEntrySide::Debit,
                    entryType: WalletLedgerEntryType::WithdrawalSettlementDebit,
                    amount: $settlementAmount,
                    currency: (string) $wr->currency,
                    referenceType: 'withdrawal_request',
                    referenceId: $wr->id,
                    counterpartyWalletId: null,
                    description: 'withdrawal_settlement',
                ),
            ],
        ));

        $wr->status = WithdrawalRequestStatus::PaidOut;
        $wr->reviewed_by = $reviewerUserId;
        $wr->reviewed_at = now();
        $wr->save();

        return [
            'withdrawal_request_id' => $wr->id,
            'status' => $wr->status->value,
            'batch_id' => (int) $ledger['batch_id'],
        ];
    }

    /**
     * @return array{withdrawal_request_id: int, status: string}
     */
    private function executeRejectWithdrawal(int $withdrawalRequestId, int $reviewerUserId, string $reason): array
    {
        $wr = WithdrawalRequest::query()->whereKey($withdrawalRequestId)->lockForUpdate()->first();
        if ($wr === null) {
            throw new WithdrawalValidationFailedException(null, 'withdrawal_request_not_found', [
                'withdrawal_request_id' => $withdrawalRequestId,
            ]);
        }

        if ($wr->status === WithdrawalRequestStatus::Rejected) {
            throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_already_rejected', []);
        }

        if ($wr->status !== WithdrawalRequestStatus::Requested) {
            throw new InvalidDomainStateTransitionException(
                aggregate: 'withdrawal_request',
                aggregateId: $wr->id,
                fromState: $wr->status->value,
                toState: WithdrawalRequestStatus::Rejected->value,
            );
        }

        if ($wr->hold_id === null) {
            throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_hold_missing', []);
        }

        Wallet::query()->whereKey($wr->wallet_id)->lockForUpdate()->firstOrFail();

        $this->walletLedgerService->releaseHold(new ReleaseWalletHoldCommand((int) $wr->hold_id));

        $wr->status = WithdrawalRequestStatus::Rejected;
        $wr->reviewed_by = $reviewerUserId;
        $wr->reviewed_at = now();
        $wr->reject_reason = $reason !== '' ? $reason : null;
        $wr->save();

        return [
            'withdrawal_request_id' => $wr->id,
            'status' => $wr->status->value,
        ];
    }

    private function assertSellerOwnsWallet(int $sellerProfileId, Wallet $wallet): void
    {
        if ($wallet->wallet_type !== WalletType::Seller) {
            throw new WithdrawalValidationFailedException(null, 'wallet_not_seller_wallet', [
                'wallet_id' => $wallet->id,
                'wallet_type' => $wallet->wallet_type->value,
            ]);
        }

        $seller = SellerProfile::query()->whereKey($sellerProfileId)->lockForUpdate()->first();
        if ($seller === null) {
            throw new WithdrawalValidationFailedException(null, 'seller_profile_not_found', [
                'seller_profile_id' => $sellerProfileId,
            ]);
        }

        if ((int) $seller->user_id !== (int) $wallet->user_id) {
            throw new WithdrawalValidationFailedException(null, 'seller_wallet_mismatch', [
                'seller_profile_id' => $sellerProfileId,
                'wallet_id' => $wallet->id,
            ]);
        }
    }

    private function assertWalletCurrency(Wallet $wallet, string $currency): void
    {
        if ((string) $wallet->currency !== $currency) {
            throw new WithdrawalValidationFailedException(null, 'withdrawal_currency_mismatch', [
                'wallet_currency' => (string) $wallet->currency,
                'requested_currency' => $currency,
            ]);
        }
    }

    private function buildRequestWithdrawalReplayPayload(string $idempotencyKey, int $sellerProfileId): array
    {
        $wr = WithdrawalRequest::query()
            ->where('idempotency_key', $idempotencyKey)
            ->firstOrFail();

        if ((int) $wr->seller_profile_id !== $sellerProfileId) {
            throw new IdempotencyConflictException($idempotencyKey, 'withdrawal_request_submit');
        }

        return [
            'withdrawal_request_id' => $wr->id,
            'status' => $wr->status->value,
            'requested_amount' => (string) $wr->requested_amount,
            'fee_amount' => (string) $wr->fee_amount,
            'net_payout_amount' => (string) $wr->net_payout_amount,
            'idempotent_replay' => true,
        ];
    }

    /**
     * @return array{withdrawal_request_id: int, status: string, batch_id?: int}
     */
    private function buildApproveReplayPayload(int $withdrawalRequestId): array
    {
        $wr = WithdrawalRequest::query()->whereKey($withdrawalRequestId)->firstOrFail();
        if ($wr->status !== WithdrawalRequestStatus::PaidOut) {
            throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_approve_replay_state_mismatch', [
                'status' => $wr->status->value,
            ]);
        }

        $batchId = WalletLedgerBatch::query()
            ->where('reference_type', 'withdrawal_request')
            ->where('reference_id', $wr->id)
            ->where('event_name', LedgerPostingEventName::Withdrawal)
            ->value('id');

        $out = [
            'withdrawal_request_id' => $wr->id,
            'status' => $wr->status->value,
            'idempotent_replay' => true,
        ];
        if ($batchId !== null) {
            $out['batch_id'] = (int) $batchId;
        }

        return $out;
    }

    /**
     * @return array{withdrawal_request_id: int, status: string}
     */
    private function buildRejectReplayPayload(int $withdrawalRequestId): array
    {
        $wr = WithdrawalRequest::query()->whereKey($withdrawalRequestId)->firstOrFail();
        if ($wr->status !== WithdrawalRequestStatus::Rejected) {
            throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_reject_replay_state_mismatch', [
                'status' => $wr->status->value,
            ]);
        }

        return [
            'withdrawal_request_id' => $wr->id,
            'status' => $wr->status->value,
            'idempotent_replay' => true,
        ];
    }

    /**
     * @return array{withdrawal_request_id: int, status: string, batch_id?: int}
     */
    private function buildReviewReplayPayload(int $withdrawalRequestId): array
    {
        $wr = WithdrawalRequest::query()->whereKey($withdrawalRequestId)->firstOrFail();

        return match ($wr->status) {
            WithdrawalRequestStatus::PaidOut => $this->buildApproveReplayPayload($withdrawalRequestId),
            WithdrawalRequestStatus::Rejected => $this->buildRejectReplayPayload($withdrawalRequestId),
            default => throw new WithdrawalValidationFailedException($wr->id, 'withdrawal_review_replay_state_mismatch', [
                'status' => $wr->status->value,
            ]),
        };
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{idempotency: IdempotencyKey, replay: bool}
     */
    private function claimIdempotency(string $scope, string $key, string $requestHash): array
    {
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

    private function toScale(string $amount): int
    {
        $normalized = trim($amount);
        if (! preg_match('/^-?\d+(\.\d{1,4})?$/', $normalized)) {
            throw new InvalidLedgerOperationException('invalid_decimal_precision');
        }

        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad($fraction, 4, '0');
        $scaled = ((int) $whole * 10000) + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }

    private function fromScale(int $scaled): string
    {
        $negative = $scaled < 0;
        $absolute = abs($scaled);
        $whole = intdiv($absolute, 10000);
        $fraction = $absolute % 10000;
        $value = sprintf('%d.%04d', $whole, $fraction);

        return $negative ? '-'.$value : $value;
    }
}

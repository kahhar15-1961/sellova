<?php

namespace App\Services\WalletLedger;

use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PlaceWalletHoldCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Commands\WalletLedger\ReleaseWalletHoldCommand;
use App\Domain\Commands\WalletLedger\ReverseLedgerBatchCommand;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\WalletAccountStatus;
use App\Domain\Enums\WalletHoldStatus;
use App\Domain\Enums\WalletLedgerBatchStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Domain\Exceptions\InvalidLedgerOperationException;
use App\Domain\Exceptions\WalletCurrencyMismatchException;
use App\Domain\Exceptions\WalletNotFoundException;
use App\Domain\Policy\WalletNegativeBalancePolicy;
use App\Domain\Value\LedgerPostingLine;
use App\Models\IdempotencyKey;
use App\Models\Wallet;
use App\Models\WalletBalanceSnapshot;
use App\Models\WalletHold;
use App\Models\WalletLedgerBatch;
use App\Models\WalletLedgerEntry;
use App\Services\Support\FinancialCritical;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletLedgerService
{
    use FinancialCritical;

    public function createWalletIfMissing(CreateWalletIfMissingCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $existing = Wallet::query()
                ->where('user_id', $command->userId)
                ->where('wallet_type', $command->walletType->value)
                ->where('currency', $command->currency)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['wallet_id' => $existing->id, 'created' => false];
            }

            try {
                $wallet = Wallet::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $command->userId,
                    'wallet_type' => $command->walletType,
                    'currency' => $command->currency,
                    'status' => WalletAccountStatus::Active,
                    'version' => 1,
                ]);
            } catch (QueryException $e) {
                $wallet = Wallet::query()
                    ->where('user_id', $command->userId)
                    ->where('wallet_type', $command->walletType->value)
                    ->where('currency', $command->currency)
                    ->lockForUpdate()
                    ->first();

                if ($wallet === null) {
                    throw $e;
                }

                return ['wallet_id' => $wallet->id, 'created' => false];
            }

            return ['wallet_id' => $wallet->id, 'created' => true];
        });
    }

    public function placeHold(PlaceWalletHoldCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $wallet = $this->lockWalletOrFail($command->walletId);
            $amountScale = $this->toScale($command->amount);
            if ($amountScale <= 0) {
                throw new InvalidLedgerOperationException('hold_amount_must_be_positive');
            }

            $existing = WalletHold::query()
                ->where('hold_type', $command->holdType->value)
                ->where('reference_type', $command->referenceType)
                ->where('reference_id', $command->referenceId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new InvalidLedgerOperationException('duplicate_hold_reference');
            }

            $balance = $this->readWalletLedgerBalanceScale($wallet->id);
            $effectiveActiveHolds = $this->readEffectiveActiveHoldsScale($wallet->id);
            $available = $balance - $effectiveActiveHolds;
            if ($available - $amountScale < 0) {
                throw new InsufficientWalletBalanceException(
                    walletId: $wallet->id,
                    currency: (string) $wallet->currency,
                    requestedAmount: $this->fromScale($amountScale),
                    availableAmount: $this->fromScale($available),
                );
            }

            try {
                $hold = WalletHold::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'wallet_id' => $wallet->id,
                    'hold_type' => $command->holdType,
                    'reference_type' => $command->referenceType,
                    'reference_id' => $command->referenceId,
                    'amount' => $this->fromScale($amountScale),
                    'currency' => $wallet->currency,
                    'status' => WalletHoldStatus::Active,
                ]);
            } catch (QueryException $e) {
                throw new InvalidLedgerOperationException('duplicate_hold_reference', previous: $e);
            }

            return [
                'wallet_hold_id' => $hold->id,
                'wallet_id' => $wallet->id,
                'amount' => $hold->amount,
                'status' => $hold->status->value,
            ];
        });
    }

    public function releaseHold(ReleaseWalletHoldCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $hold = WalletHold::query()
                ->whereKey($command->walletHoldId)
                ->lockForUpdate()
                ->first();

            if ($hold === null) {
                throw new InvalidLedgerOperationException('hold_not_found');
            }

            $wallet = $this->lockWalletOrFail((int) $hold->wallet_id);
            if ($hold->currency !== null && $hold->currency !== $wallet->currency) {
                throw new WalletCurrencyMismatchException(
                    walletId: $wallet->id,
                    walletCurrency: (string) $wallet->currency,
                    requestedCurrency: (string) $hold->currency,
                );
            }

            if ($hold->status !== WalletHoldStatus::Active) {
                throw new InvalidLedgerOperationException('hold_not_active');
            }

            $hold->status = WalletHoldStatus::Released;
            $hold->save();

            return [
                'wallet_hold_id' => $hold->id,
                'wallet_id' => $wallet->id,
                'status' => $hold->status->value,
            ];
        });
    }

    public function postLedgerBatch(PostLedgerBatchCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            if ($command->entries === []) {
                throw new InvalidLedgerOperationException('ledger_entries_required');
            }

            $requestHash = hash('sha256', json_encode([
                'event_name' => $command->eventName->value,
                'reference_type' => $command->referenceType,
                'reference_id' => $command->referenceId,
                'entries' => array_map(static fn (LedgerPostingLine $line): array => [
                    'wallet_id' => $line->walletId,
                    'entry_side' => $line->entrySide->value,
                    'entry_type' => $line->entryType->value,
                    'amount' => $line->amount,
                    'currency' => $line->currency,
                    'reference_type' => $line->referenceType,
                    'reference_id' => $line->referenceId,
                    'counterparty_wallet_id' => $line->counterpartyWalletId,
                    'description' => $line->description,
                ], $command->entries),
            ], JSON_THROW_ON_ERROR));

            $idempotency = IdempotencyKey::query()
                ->where('scope', 'wallet_ledger_posting')
                ->where('key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($idempotency !== null) {
                if ($idempotency->request_hash !== $requestHash) {
                    throw new IdempotencyConflictException($command->idempotencyKey, 'wallet_ledger_posting');
                }

                if ($idempotency->status === IdempotencyKeyStatus::Succeeded) {
                    $existingBatch = WalletLedgerBatch::query()
                        ->where('idempotency_key_id', $idempotency->id)
                        ->first();

                    if ($existingBatch === null) {
                        throw new InvalidLedgerOperationException('idempotency_succeeded_without_batch');
                    }

                    return [
                        'batch_id' => $existingBatch->id,
                        'status' => $existingBatch->status->value,
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException($command->idempotencyKey, 'wallet_ledger_posting');
            }

            try {
                $idempotency = IdempotencyKey::query()->create([
                    'key' => $command->idempotencyKey,
                    'scope' => 'wallet_ledger_posting',
                    'request_hash' => $requestHash,
                    'status' => IdempotencyKeyStatus::Started,
                    'expires_at' => now()->addDay(),
                ]);
            } catch (QueryException $e) {
                $existing = IdempotencyKey::query()
                    ->where('scope', 'wallet_ledger_posting')
                    ->where('key', $command->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && $existing->request_hash === $requestHash && $existing->status === IdempotencyKeyStatus::Succeeded) {
                    $existingBatch = WalletLedgerBatch::query()
                        ->where('idempotency_key_id', $existing->id)
                        ->first();

                    if ($existingBatch !== null) {
                        return [
                            'batch_id' => $existingBatch->id,
                            'status' => $existingBatch->status->value,
                            'idempotent_replay' => true,
                        ];
                    }
                }

                throw new IdempotencyConflictException($command->idempotencyKey, 'wallet_ledger_posting', previous: $e);
            }

            $walletIds = [];
            foreach ($command->entries as $line) {
                if (! $line instanceof LedgerPostingLine) {
                    throw new InvalidLedgerOperationException('invalid_posting_line_type');
                }

                $walletIds[] = $line->walletId;
                if ($line->counterpartyWalletId !== null) {
                    $walletIds[] = $line->counterpartyWalletId;
                }
            }

            $walletIds = array_values(array_unique($walletIds));
            sort($walletIds);

            /** @var array<int, Wallet> $walletsById */
            $walletsById = Wallet::query()
                ->whereIn('id', $walletIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all();

            foreach ($walletIds as $walletId) {
                if (! isset($walletsById[$walletId])) {
                    throw new WalletNotFoundException($walletId);
                }
            }

            $batch = WalletLedgerBatch::query()->create([
                'uuid' => (string) Str::uuid(),
                'event_name' => $command->eventName,
                'reference_type' => $command->referenceType,
                'reference_id' => $command->referenceId,
                'idempotency_key_id' => $idempotency->id,
                'status' => WalletLedgerBatchStatus::Posted,
                'posted_at' => now(),
            ]);

            $runningBalances = [];
            $activeHolds = [];

            foreach ($walletIds as $walletId) {
                $runningBalances[$walletId] = $this->readWalletLedgerBalanceScale($walletId);
                $activeHolds[$walletId] = $this->readEffectiveActiveHoldsScale($walletId);
            }

            foreach ($command->entries as $line) {
                $wallet = $walletsById[$line->walletId];
                $this->assertCurrencyMatches($wallet, $line->currency);
                $this->assertLineAllowedForEvent($command->eventName, $line);

                $amount = $this->toScale($line->amount);
                if ($amount <= 0) {
                    throw new InvalidLedgerOperationException('entry_amount_must_be_positive');
                }

                $currentBalance = $runningBalances[$wallet->id];
                $newBalance = $line->entrySide === WalletLedgerEntrySide::Credit
                    ? $currentBalance + $amount
                    : $currentBalance - $amount;

                $effectiveActiveHolds = $activeHolds[$wallet->id];
                if ($command->eventName === LedgerPostingEventName::EscrowHold
                    && $line->entryType === WalletLedgerEntryType::EscrowHoldDebit
                    && $line->referenceType === 'escrow_account') {
                    $effectiveActiveHolds -= $this->activeEscrowReservationScale($wallet->id, $line->referenceId);
                }

                $availableAfter = $newBalance - $effectiveActiveHolds;
                if ($availableAfter < 0 && ! $this->allowsNegativeAvailable($line->entryType)) {
                    throw new InsufficientWalletBalanceException(
                        walletId: $wallet->id,
                        currency: (string) $wallet->currency,
                        requestedAmount: $line->amount,
                        availableAmount: $this->fromScale($currentBalance - $effectiveActiveHolds),
                    );
                }

                $entry = WalletLedgerEntry::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'batch_id' => $batch->id,
                    'wallet_id' => $wallet->id,
                    'entry_side' => $line->entrySide,
                    'entry_type' => $line->entryType,
                    'amount' => $this->fromScale($amount),
                    'currency' => $wallet->currency,
                    'running_balance_after' => $this->fromScale($newBalance),
                    'reference_type' => $line->referenceType,
                    'reference_id' => $line->referenceId,
                    'counterparty_wallet_id' => $line->counterpartyWalletId,
                    'occurred_at' => now(),
                    'is_reversal' => false,
                    'description' => $line->description,
                ]);

                $runningBalances[$wallet->id] = $newBalance;
            }

            $idempotency->status = IdempotencyKeyStatus::Succeeded;
            $idempotency->response_hash = hash('sha256', (string) $batch->id);
            $idempotency->save();

            return [
                'batch_id' => $batch->id,
                'status' => $batch->status->value,
                'idempotent_replay' => false,
            ];
        });
    }

    public function reverseLedgerBatch(ReverseLedgerBatchCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $batch = WalletLedgerBatch::query()
                ->whereKey($command->batchId)
                ->lockForUpdate()
                ->first();

            if ($batch === null) {
                throw new InvalidLedgerOperationException('batch_not_found');
            }

            if ($batch->status !== WalletLedgerBatchStatus::Posted) {
                throw new InvalidLedgerOperationException('batch_not_reversible');
            }

            $entries = WalletLedgerEntry::query()
                ->where('batch_id', $batch->id)
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                throw new InvalidLedgerOperationException('batch_has_no_entries');
            }

            $walletIds = $entries->pluck('wallet_id')->unique()->sort()->values()->all();
            /** @var array<int, Wallet> $walletsById */
            $walletsById = Wallet::query()
                ->whereIn('id', $walletIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all();

            foreach ($walletIds as $walletId) {
                if (! isset($walletsById[$walletId])) {
                    throw new WalletNotFoundException($walletId);
                }
            }

            $reverseKey = sprintf('reverse:%d:%s', $batch->id, $command->reasonCode);
            $requestHash = hash('sha256', $reverseKey);

            $idempotency = IdempotencyKey::query()
                ->where('scope', 'wallet_ledger_reversal')
                ->where('key', $reverseKey)
                ->lockForUpdate()
                ->first();

            if ($idempotency !== null) {
                if ($idempotency->request_hash !== $requestHash) {
                    throw new IdempotencyConflictException($reverseKey, 'wallet_ledger_reversal');
                }

                if ($idempotency->status === IdempotencyKeyStatus::Succeeded) {
                    $existing = WalletLedgerBatch::query()
                        ->where('idempotency_key_id', $idempotency->id)
                        ->first();

                    if ($existing !== null) {
                        return ['batch_id' => $existing->id, 'status' => $existing->status->value, 'idempotent_replay' => true];
                    }
                }

                throw new IdempotencyConflictException($reverseKey, 'wallet_ledger_reversal');
            }

            try {
                $idempotency = IdempotencyKey::query()->create([
                    'key' => $reverseKey,
                    'scope' => 'wallet_ledger_reversal',
                    'request_hash' => $requestHash,
                    'status' => IdempotencyKeyStatus::Started,
                    'expires_at' => now()->addDay(),
                ]);
            } catch (QueryException $e) {
                $existing = IdempotencyKey::query()
                    ->where('scope', 'wallet_ledger_reversal')
                    ->where('key', $reverseKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && $existing->request_hash === $requestHash && $existing->status === IdempotencyKeyStatus::Succeeded) {
                    $existingBatch = WalletLedgerBatch::query()
                        ->where('idempotency_key_id', $existing->id)
                        ->first();
                    if ($existingBatch !== null) {
                        return ['batch_id' => $existingBatch->id, 'status' => $existingBatch->status->value, 'idempotent_replay' => true];
                    }
                }

                throw new IdempotencyConflictException($reverseKey, 'wallet_ledger_reversal', previous: $e);
            }

            $reversalBatch = WalletLedgerBatch::query()->create([
                'uuid' => (string) Str::uuid(),
                'event_name' => LedgerPostingEventName::Adjustment,
                'reference_type' => 'wallet_ledger_batch',
                'reference_id' => $batch->id,
                'idempotency_key_id' => $idempotency->id,
                'status' => WalletLedgerBatchStatus::Posted,
                'posted_at' => now(),
            ]);

            $runningBalances = [];
            $activeHolds = [];
            foreach ($walletIds as $walletId) {
                $runningBalances[$walletId] = $this->readWalletLedgerBalanceScale($walletId);
                $activeHolds[$walletId] = $this->readEffectiveActiveHoldsScale($walletId);
            }

            foreach ($entries as $entry) {
                $walletId = (int) $entry->wallet_id;
                $wallet = $walletsById[$walletId];
                $amount = $this->toScale((string) $entry->amount);

                $reverseSide = $entry->entry_side === WalletLedgerEntrySide::Credit
                    ? WalletLedgerEntrySide::Debit
                    : WalletLedgerEntrySide::Credit;
                $reverseType = $reverseSide === WalletLedgerEntrySide::Credit
                    ? WalletLedgerEntryType::AdjustmentCredit
                    : WalletLedgerEntryType::AdjustmentDebit;

                $current = $runningBalances[$walletId];
                $new = $reverseSide === WalletLedgerEntrySide::Credit ? $current + $amount : $current - $amount;
                $availableAfter = $new - $activeHolds[$walletId];

                if ($availableAfter < 0 && ! $this->allowsNegativeAvailable($reverseType)) {
                    throw new InsufficientWalletBalanceException(
                        walletId: $walletId,
                        currency: (string) $wallet->currency,
                        requestedAmount: $this->fromScale($amount),
                        availableAmount: $this->fromScale($current - $activeHolds[$walletId]),
                    );
                }

                WalletLedgerEntry::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'batch_id' => $reversalBatch->id,
                    'wallet_id' => $walletId,
                    'entry_side' => $reverseSide,
                    'entry_type' => $reverseType,
                    'amount' => $this->fromScale($amount),
                    'currency' => $wallet->currency,
                    'running_balance_after' => $this->fromScale($new),
                    'reference_type' => 'wallet_ledger_entry',
                    'reference_id' => $entry->id,
                    'counterparty_wallet_id' => $entry->counterparty_wallet_id,
                    'occurred_at' => now(),
                    'reversal_of_entry_id' => $entry->id,
                    'is_reversal' => true,
                    'description' => 'batch_reversal:'.$command->reasonCode,
                ]);

                $runningBalances[$walletId] = $new;
            }

            $batch->status = WalletLedgerBatchStatus::Reversed;
            $batch->reversed_at = now();
            $batch->save();

            $idempotency->status = IdempotencyKeyStatus::Succeeded;
            $idempotency->response_hash = hash('sha256', (string) $reversalBatch->id);
            $idempotency->save();

            return ['batch_id' => $reversalBatch->id, 'status' => $reversalBatch->status->value, 'idempotent_replay' => false];
        });
    }

    public function computeWalletBalances(ComputeWalletBalancesCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $wallet = $this->lockWalletOrFail($command->walletId);
            $ledger = $this->readWalletLedgerBalanceScale($wallet->id);
            $held = $this->readActiveHoldsScale($wallet->id);
            $effectiveHeld = $this->readEffectiveActiveHoldsScale($wallet->id, $held);
            $available = $ledger - $effectiveHeld;
            $asOf = $this->nextAvailableSnapshotAsOf($wallet->id);

            $snapshot = WalletBalanceSnapshot::query()->create([
                'wallet_id' => $wallet->id,
                'as_of' => $asOf,
                'available_balance' => $this->fromScale($available),
                'held_balance' => $this->fromScale($held),
            ]);

            return [
                'wallet_id' => $wallet->id,
                'currency' => $wallet->currency,
                'available_balance' => $snapshot->available_balance,
                'held_balance' => $snapshot->held_balance,
                'as_of' => optional($snapshot->as_of)?->toISOString(),
            ];
        });
    }

    private function nextAvailableSnapshotAsOf(int $walletId): \Illuminate\Support\Carbon
    {
        // Eloquent persists datetimes to second precision in this project setup.
        $candidate = now()->copy()->startOfSecond();
        $latest = WalletBalanceSnapshot::query()
            ->where('wallet_id', $walletId)
            ->orderByDesc('as_of')
            ->lockForUpdate()
            ->first();

        if ($latest !== null && $latest->as_of !== null) {
            $latestAsOf = $latest->as_of->copy();
            if ($latestAsOf->greaterThanOrEqualTo($candidate)) {
                return $latestAsOf->addSecond();
            }
        }

        return $candidate;
    }

    private function lockWalletOrFail(int $walletId): Wallet
    {
        $wallet = Wallet::query()->whereKey($walletId)->lockForUpdate()->first();
        if ($wallet === null) {
            throw new WalletNotFoundException($walletId);
        }

        return $wallet;
    }

    private function assertLineAllowedForEvent(LedgerPostingEventName $eventName, LedgerPostingLine $line): void
    {
        $allowed = match ($eventName) {
            LedgerPostingEventName::Deposit,
            LedgerPostingEventName::PaymentCapture => [
                [WalletLedgerEntryType::DepositCredit, WalletLedgerEntrySide::Credit],
            ],
            LedgerPostingEventName::EscrowHold => [
                [WalletLedgerEntryType::EscrowHoldDebit, WalletLedgerEntrySide::Debit],
            ],
            LedgerPostingEventName::Release => [
                [WalletLedgerEntryType::EscrowReleaseCredit, WalletLedgerEntrySide::Credit],
            ],
            LedgerPostingEventName::Refund,
            LedgerPostingEventName::PartialRefund => [
                [WalletLedgerEntryType::RefundCredit, WalletLedgerEntrySide::Credit],
            ],
            LedgerPostingEventName::Fee => [
                [WalletLedgerEntryType::PlatformFeeCredit, WalletLedgerEntrySide::Credit],
            ],
            LedgerPostingEventName::WithdrawalRequest => [
                [WalletLedgerEntryType::WithdrawalHoldDebit, WalletLedgerEntrySide::Debit],
            ],
            LedgerPostingEventName::Withdrawal => [
                [WalletLedgerEntryType::WithdrawalSettlementDebit, WalletLedgerEntrySide::Debit],
            ],
            LedgerPostingEventName::Adjustment => [
                [WalletLedgerEntryType::AdjustmentCredit, WalletLedgerEntrySide::Credit],
                [WalletLedgerEntryType::AdjustmentDebit, WalletLedgerEntrySide::Debit],
            ],
        };

        foreach ($allowed as [$type, $side]) {
            if ($line->entryType === $type && $line->entrySide === $side) {
                return;
            }
        }

        throw new InvalidLedgerOperationException('event_entry_type_mismatch');
    }

    private function assertCurrencyMatches(Wallet $wallet, string $currency): void
    {
        if ($wallet->currency !== $currency) {
            throw new WalletCurrencyMismatchException($wallet->id, (string) $wallet->currency, $currency);
        }
    }

    private function allowsNegativeAvailable(WalletLedgerEntryType $entryType): bool
    {
        return WalletNegativeBalancePolicy::allowsOverdrawForEntryType($entryType);
    }

    private function readWalletLedgerBalanceScale(int $walletId): int
    {
        $latest = WalletLedgerEntry::query()
            ->where('wallet_id', $walletId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        return $latest === null ? 0 : $this->toScale((string) $latest->running_balance_after);
    }

    private function readActiveHoldsScale(int $walletId): int
    {
        $sum = WalletHold::query()
            ->where('wallet_id', $walletId)
            ->where('status', WalletHoldStatus::Active)
            ->lockForUpdate()
            ->sum('amount');

        return $this->toScale((string) $sum);
    }

    private function activeEscrowReservationScale(int $walletId, int $escrowAccountId): int
    {
        $sum = WalletHold::query()
            ->where('wallet_id', $walletId)
            ->where('hold_type', 'escrow')
            ->where('reference_type', 'escrow_account')
            ->where('reference_id', $escrowAccountId)
            ->where('status', WalletHoldStatus::Active)
            ->lockForUpdate()
            ->sum('amount');

        return $this->toScale((string) $sum);
    }

    private function readEffectiveActiveHoldsScale(int $walletId, ?int $activeHoldsScale = null): int
    {
        $active = $activeHoldsScale ?? $this->readActiveHoldsScale($walletId);
        $debitedEscrowReservations = $this->readDebitedEscrowReservationsScale($walletId);
        $effective = $active - $debitedEscrowReservations;

        return $effective > 0 ? $effective : 0;
    }

    private function readDebitedEscrowReservationsScale(int $walletId): int
    {
        $sum = (string) DB::table('wallet_holds as h')
            ->where('h.wallet_id', $walletId)
            ->where('h.hold_type', 'escrow')
            ->where('h.reference_type', 'escrow_account')
            ->where('h.status', WalletHoldStatus::Active->value)
            ->whereExists(function ($q) use ($walletId): void {
                $q->select(DB::raw(1))
                    ->from('wallet_ledger_entries as e')
                    ->whereColumn('e.reference_id', 'h.reference_id')
                    ->where('e.wallet_id', $walletId)
                    ->where('e.reference_type', 'escrow_account')
                    ->where('e.entry_type', WalletLedgerEntryType::EscrowHoldDebit->value);
            })
            ->lockForUpdate()
            ->sum('h.amount');

        return $this->toScale($sum);
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

<?php

namespace App\Services\Escrow;

use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\Escrow\MarkEscrowUnderDisputeCommand;
use App\Domain\Commands\Escrow\RefundEscrowCommand;
use App\Domain\Commands\Escrow\ReleaseEscrowCommand;
use App\Domain\Commands\WalletLedger\PlaceWalletHoldCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\EscrowState;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\WalletHoldStatus;
use App\Domain\Enums\WalletHoldType;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\EscrowReleaseConflictException;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidEscrowStateTransitionException;
use App\Domain\Exceptions\InvalidLedgerOperationException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Domain\Value\LedgerPostingLine;
use App\Models\EscrowAccount;
use App\Models\EscrowEvent;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Services\Support\FinancialCritical;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EscrowService
{
    use FinancialCritical;

    public function __construct(
        private readonly WalletLedgerService $walletLedgerService = new WalletLedgerService(),
    ) {
    }

    public function createEscrowForOrder(CreateEscrowForOrderCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $order = Order::query()->whereKey($command->orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException(
                    orderId: $command->orderId,
                    reasonCode: 'order_not_found',
                );
            }

            $heldScale = $this->toScale($command->heldAmount);
            if ($heldScale <= 0) {
                throw new InvalidLedgerOperationException('escrow_held_amount_must_be_positive');
            }
            if ((string) $order->currency !== $command->currency) {
                throw new OrderValidationFailedException(
                    orderId: $order->id,
                    reasonCode: 'order_currency_mismatch',
                    violations: ['order_currency' => (string) $order->currency, 'escrow_currency' => $command->currency],
                );
            }

            $requestHash = $this->hashPayload([
                'order_id' => $command->orderId,
                'currency' => $command->currency,
                'held_amount' => $command->heldAmount,
            ]);
            $idem = $this->claimEscrowIdempotency(
                scope: 'escrow_create',
                key: $command->idempotencyKey,
                requestHash: $requestHash,
            );

            $existingEscrow = EscrowAccount::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();
            if ($existingEscrow !== null) {
                if ($idem['replay']) {
                    return [
                        'escrow_account_id' => $existingEscrow->id,
                        'state' => $existingEscrow->state->value,
                        'idempotent_replay' => true,
                    ];
                }

                throw new InvalidLedgerOperationException('escrow_already_exists_for_order');
            }

            $escrow = EscrowAccount::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'state' => EscrowState::Initiated,
                'currency' => $command->currency,
                'held_amount' => $this->fromScale($heldScale),
                'released_amount' => '0.0000',
                'refunded_amount' => '0.0000',
                'version' => 1,
            ]);

            EscrowEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'escrow_account_id' => $escrow->id,
                'event_type' => 'initiated',
                'amount' => $escrow->held_amount,
                'currency' => $escrow->currency,
                'from_state' => null,
                'to_state' => EscrowState::Initiated->value,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'idempotency_key_id' => $idem['idempotency']->id,
            ]);

            $this->markIdempotencySucceeded($idem['idempotency'], ['escrow_account_id' => $escrow->id]);

            return [
                'escrow_account_id' => $escrow->id,
                'state' => $escrow->state->value,
                'idempotent_replay' => false,
            ];
        });
    }

    public function holdEscrow(HoldEscrowCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $escrow = $this->lockEscrowOrFail($command->escrowAccountId);
            if ($escrow->state !== EscrowState::Initiated) {
                throw new InvalidEscrowStateTransitionException(
                    escrowAccountId: $escrow->id,
                    fromState: $escrow->state->value,
                    toState: EscrowState::Held->value,
                );
            }

            $requestHash = $this->hashPayload(['escrow_account_id' => $escrow->id]);
            $idem = $this->claimEscrowIdempotency('escrow_hold', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return [
                    'escrow_account_id' => $escrow->id,
                    'state' => $escrow->state->value,
                    'idempotent_replay' => true,
                ];
            }

            $order = Order::query()->whereKey($escrow->order_id)->lockForUpdate()->first();
            if ($order === null) {
                throw new OrderValidationFailedException($escrow->order_id, 'escrow_order_not_found');
            }

            $buyerWalletId = $this->resolveWalletIdByUser(
                userId: (int) $order->buyer_user_id,
                walletType: WalletType::Buyer,
                currency: (string) $escrow->currency,
            );

            $this->walletLedgerService->placeHold(new PlaceWalletHoldCommand(
                walletId: $buyerWalletId,
                holdType: WalletHoldType::Escrow,
                referenceType: 'escrow_account',
                referenceId: $escrow->id,
                amount: (string) $escrow->held_amount,
            ));

            $this->walletLedgerService->postLedgerBatch(new PostLedgerBatchCommand(
                eventName: LedgerPostingEventName::EscrowHold,
                referenceType: 'escrow_account',
                referenceId: $escrow->id,
                idempotencyKey: $command->idempotencyKey.':ledger:hold',
                entries: [
                    new LedgerPostingLine(
                        walletId: $buyerWalletId,
                        entrySide: WalletLedgerEntrySide::Debit,
                        entryType: WalletLedgerEntryType::EscrowHoldDebit,
                        amount: (string) $escrow->held_amount,
                        currency: (string) $escrow->currency,
                        referenceType: 'escrow_account',
                        referenceId: $escrow->id,
                        counterpartyWalletId: null,
                        description: 'escrow_hold',
                    ),
                ],
            ));

            $from = $escrow->state;
            $escrow->state = EscrowState::Held;
            $escrow->held_at = now();
            $escrow->save();

            EscrowEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'escrow_account_id' => $escrow->id,
                'event_type' => 'hold',
                'amount' => $escrow->held_amount,
                'currency' => $escrow->currency,
                'from_state' => $from->value,
                'to_state' => EscrowState::Held->value,
                'reference_type' => 'escrow_account',
                'reference_id' => $escrow->id,
                'idempotency_key_id' => $idem['idempotency']->id,
            ]);

            $this->markIdempotencySucceeded($idem['idempotency'], ['escrow_account_id' => $escrow->id, 'state' => $escrow->state->value]);

            return [
                'escrow_account_id' => $escrow->id,
                'state' => $escrow->state->value,
                'idempotent_replay' => false,
            ];
        });
    }

    public function releaseEscrow(ReleaseEscrowCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $escrow = $this->lockEscrowOrFail($command->escrowAccountId);
            if ($escrow->state === EscrowState::UnderDispute) {
                throw new EscrowReleaseConflictException(
                    escrowAccountId: $escrow->id,
                    reasonCode: 'escrow_frozen_under_dispute',
                );
            }
            if (! in_array($escrow->state, [EscrowState::Held], true)) {
                throw new InvalidEscrowStateTransitionException(
                    escrowAccountId: $escrow->id,
                    fromState: $escrow->state->value,
                    toState: EscrowState::Released->value,
                );
            }

            $requestHash = $this->hashPayload(['escrow_account_id' => $escrow->id]);
            $idem = $this->claimEscrowIdempotency('escrow_release', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return ['escrow_account_id' => $escrow->id, 'state' => $escrow->state->value, 'idempotent_replay' => true];
            }

            $remaining = $this->remainingEscrowScale($escrow);
            if ($remaining <= 0) {
                throw new EscrowReleaseConflictException($escrow->id, 'no_releasable_amount_remaining');
            }
            if ($this->toScale((string) $escrow->refunded_amount) > 0) {
                throw new EscrowReleaseConflictException($escrow->id, 'split_settlement_not_allowed');
            }

            [$buyerWalletId, $sellerWalletId] = $this->resolveEscrowCounterpartyWallets($escrow);
            $amount = $this->fromScale($remaining);

            $this->walletLedgerService->postLedgerBatch(new PostLedgerBatchCommand(
                eventName: LedgerPostingEventName::Release,
                referenceType: 'escrow_account',
                referenceId: $escrow->id,
                idempotencyKey: $command->idempotencyKey.':ledger:release',
                entries: [
                    new LedgerPostingLine(
                        walletId: $sellerWalletId,
                        entrySide: WalletLedgerEntrySide::Credit,
                        entryType: WalletLedgerEntryType::EscrowReleaseCredit,
                        amount: $amount,
                        currency: (string) $escrow->currency,
                        referenceType: 'escrow_account',
                        referenceId: $escrow->id,
                        counterpartyWalletId: $buyerWalletId,
                        description: 'escrow_release',
                    ),
                ],
            ));

            $from = $escrow->state;
            $escrow->released_amount = $this->fromScale($this->toScale((string) $escrow->released_amount) + $remaining);
            $this->applyTerminalStateAfterSettlement($escrow, EscrowState::Released);
            $escrow->save();
            $this->consumeEscrowHoldIfTerminal($escrow);

            EscrowEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'escrow_account_id' => $escrow->id,
                'event_type' => 'release',
                'amount' => $amount,
                'currency' => $escrow->currency,
                'from_state' => $from->value,
                'to_state' => $escrow->state->value,
                'reference_type' => 'escrow_account',
                'reference_id' => $escrow->id,
                'idempotency_key_id' => $idem['idempotency']->id,
            ]);

            $this->markIdempotencySucceeded($idem['idempotency'], ['escrow_account_id' => $escrow->id, 'state' => $escrow->state->value]);

            return [
                'escrow_account_id' => $escrow->id,
                'state' => $escrow->state->value,
                'released_amount' => (string) $escrow->released_amount,
                'refunded_amount' => (string) $escrow->refunded_amount,
                'idempotent_replay' => false,
            ];
        });
    }

    public function refundEscrow(RefundEscrowCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $escrow = $this->lockEscrowOrFail($command->escrowAccountId);
            if (! in_array($escrow->state, [EscrowState::Held, EscrowState::UnderDispute], true)) {
                throw new InvalidEscrowStateTransitionException(
                    escrowAccountId: $escrow->id,
                    fromState: $escrow->state->value,
                    toState: EscrowState::Refunded->value,
                );
            }

            $requestHash = $this->hashPayload([
                'escrow_account_id' => $escrow->id,
                'refund_amount' => $command->refundAmount,
            ]);
            $idem = $this->claimEscrowIdempotency('escrow_refund', $command->idempotencyKey, $requestHash);
            if ($idem['replay']) {
                return ['escrow_account_id' => $escrow->id, 'state' => $escrow->state->value, 'idempotent_replay' => true];
            }

            $remaining = $this->remainingEscrowScale($escrow);
            if ($remaining <= 0) {
                throw new EscrowReleaseConflictException($escrow->id, 'no_refundable_amount_remaining');
            }

            $requested = $command->refundAmount === null ? $remaining : $this->toScale($command->refundAmount);
            if ($requested <= 0 || $requested > $remaining) {
                throw new EscrowReleaseConflictException($escrow->id, 'invalid_refund_amount');
            }
            if ($this->toScale((string) $escrow->released_amount) > 0) {
                throw new EscrowReleaseConflictException($escrow->id, 'split_settlement_not_allowed');
            }

            [$buyerWalletId, $sellerWalletId] = $this->resolveEscrowCounterpartyWallets($escrow);
            $refundEvent = $requested === $remaining ? LedgerPostingEventName::Refund : LedgerPostingEventName::PartialRefund;
            $amount = $this->fromScale($requested);

            $this->walletLedgerService->postLedgerBatch(new PostLedgerBatchCommand(
                eventName: $refundEvent,
                referenceType: 'escrow_account',
                referenceId: $escrow->id,
                idempotencyKey: $command->idempotencyKey.':ledger:refund',
                entries: [
                    new LedgerPostingLine(
                        walletId: $buyerWalletId,
                        entrySide: WalletLedgerEntrySide::Credit,
                        entryType: WalletLedgerEntryType::RefundCredit,
                        amount: $amount,
                        currency: (string) $escrow->currency,
                        referenceType: 'escrow_account',
                        referenceId: $escrow->id,
                        counterpartyWalletId: $sellerWalletId,
                        description: 'escrow_refund',
                    ),
                ],
            ));

            $from = $escrow->state;
            $escrow->refunded_amount = $this->fromScale($this->toScale((string) $escrow->refunded_amount) + $requested);
            $this->applyTerminalStateAfterSettlement($escrow, EscrowState::Refunded);
            $escrow->save();
            $this->consumeEscrowHoldIfTerminal($escrow);

            EscrowEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'escrow_account_id' => $escrow->id,
                'event_type' => 'refund',
                'amount' => $amount,
                'currency' => $escrow->currency,
                'from_state' => $from->value,
                'to_state' => $escrow->state->value,
                'reference_type' => 'escrow_account',
                'reference_id' => $escrow->id,
                'idempotency_key_id' => $idem['idempotency']->id,
            ]);

            $this->markIdempotencySucceeded($idem['idempotency'], ['escrow_account_id' => $escrow->id, 'state' => $escrow->state->value]);

            return [
                'escrow_account_id' => $escrow->id,
                'state' => $escrow->state->value,
                'released_amount' => (string) $escrow->released_amount,
                'refunded_amount' => (string) $escrow->refunded_amount,
                'idempotent_replay' => false,
            ];
        });
    }

    public function markUnderDispute(MarkEscrowUnderDisputeCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $escrow = $this->lockEscrowOrFail($command->escrowAccountId);
            if ($escrow->state !== EscrowState::Held) {
                throw new InvalidEscrowStateTransitionException(
                    escrowAccountId: $escrow->id,
                    fromState: $escrow->state->value,
                    toState: EscrowState::UnderDispute->value,
                );
            }

            $from = $escrow->state;
            $escrow->state = EscrowState::UnderDispute;
            $escrow->save();

            EscrowEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'escrow_account_id' => $escrow->id,
                'event_type' => 'dispute_opened',
                'amount' => '0.0000',
                'currency' => $escrow->currency,
                'from_state' => $from->value,
                'to_state' => EscrowState::UnderDispute->value,
                'reference_type' => 'dispute_case',
                'reference_id' => $command->disputeCaseId,
            ]);

            return [
                'escrow_account_id' => $escrow->id,
                'state' => $escrow->state->value,
                'idempotent_replay' => false,
            ];
        });
    }

    private function lockEscrowOrFail(int $escrowAccountId): EscrowAccount
    {
        $escrow = EscrowAccount::query()->whereKey($escrowAccountId)->lockForUpdate()->first();
        if ($escrow === null) {
            throw new InvalidLedgerOperationException('escrow_account_not_found');
        }

        return $escrow;
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{idempotency: IdempotencyKey, replay: bool}
     */
    private function claimEscrowIdempotency(string $scope, string $key, string $requestHash): array
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

    private function remainingEscrowScale(EscrowAccount $escrow): int
    {
        $held = $this->toScale((string) $escrow->held_amount);
        $released = $this->toScale((string) $escrow->released_amount);
        $refunded = $this->toScale((string) $escrow->refunded_amount);
        $remaining = $held - ($released + $refunded);
        if ($remaining < 0) {
            throw new EscrowReleaseConflictException($escrow->id, 'escrow_conservation_violation');
        }

        return $remaining;
    }

    private function applyTerminalStateAfterSettlement(EscrowAccount $escrow, EscrowState $terminalWhenExhausted): void
    {
        $remaining = $this->remainingEscrowScale($escrow);
        if ($remaining === 0) {
            $escrow->state = $terminalWhenExhausted;
            $escrow->closed_at = now();
        }
    }

    private function consumeEscrowHoldIfTerminal(EscrowAccount $escrow): void
    {
        if (! in_array($escrow->state, [EscrowState::Released, EscrowState::Refunded], true)) {
            return;
        }

        $hold = \App\Models\WalletHold::query()
            ->where('hold_type', WalletHoldType::Escrow->value)
            ->where('reference_type', 'escrow_account')
            ->where('reference_id', $escrow->id)
            ->lockForUpdate()
            ->first();

        if ($hold === null) {
            throw new InvalidLedgerOperationException('escrow_hold_missing_on_terminal_settlement');
        }

        if ($hold->status === WalletHoldStatus::Active) {
            $hold->status = WalletHoldStatus::Consumed;
            $hold->save();
        }
    }

    /**
     * @return array{0:int,1:int} [buyerWalletId, sellerWalletId]
     */
    private function resolveEscrowCounterpartyWallets(EscrowAccount $escrow): array
    {
        $order = Order::query()
            ->with('orderItems')
            ->whereKey($escrow->order_id)
            ->lockForUpdate()
            ->first();
        if ($order === null) {
            throw new OrderValidationFailedException($escrow->order_id, 'escrow_order_not_found');
        }

        $buyerWalletId = $this->resolveWalletIdByUser(
            userId: (int) $order->buyer_user_id,
            walletType: WalletType::Buyer,
            currency: (string) $escrow->currency,
        );

        $sellerProfileIds = $order->orderItems->pluck('seller_profile_id')->filter()->unique()->values();
        if ($sellerProfileIds->count() !== 1) {
            throw new EscrowReleaseConflictException($escrow->id, 'multi_seller_release_not_supported_yet');
        }

        $sellerProfile = SellerProfile::query()->whereKey((int) $sellerProfileIds->first())->lockForUpdate()->first();
        if ($sellerProfile === null) {
            throw new EscrowReleaseConflictException($escrow->id, 'seller_profile_not_found');
        }

        $sellerWalletId = $this->resolveWalletIdByUser(
            userId: (int) $sellerProfile->user_id,
            walletType: WalletType::Seller,
            currency: (string) $escrow->currency,
        );

        return [$buyerWalletId, $sellerWalletId];
    }

    private function resolveWalletIdByUser(int $userId, WalletType $walletType, string $currency): int
    {
        $wallet = \App\Models\Wallet::query()
            ->where('user_id', $userId)
            ->where('wallet_type', $walletType->value)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if ($wallet === null) {
            throw new InvalidLedgerOperationException(sprintf(
                'wallet_not_found_for_user_type_currency:user=%d,type=%s,currency=%s',
                $userId,
                $walletType->value,
                $currency,
            ));
        }

        return $wallet->id;
    }
}

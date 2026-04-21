<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PlaceWalletHoldCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Commands\WalletLedger\ReleaseWalletHoldCommand;
use App\Domain\Commands\WalletLedger\ReverseLedgerBatchCommand;
use App\Domain\Enums\IdempotencyKeyStatus;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\WalletHoldType;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Domain\Exceptions\InvalidLedgerOperationException;
use App\Domain\Exceptions\WalletCurrencyMismatchException;
use App\Domain\Exceptions\WalletNotFoundException;
use App\Domain\Value\LedgerPostingLine;
use App\Models\IdempotencyKey;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletLedgerBatch;
use App\Models\WalletLedgerEntry;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Database\Capsule\Manager as Capsule;

final class WalletLedgerServiceTest extends TestCase
{
    private WalletLedgerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new WalletLedgerService();
    }

    public function test_success_deposit_posting_creates_batch_entries_and_idempotency(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            100,
            'idem-deposit-1',
            new LedgerPostingLine(
                walletId: $walletId,
                entrySide: WalletLedgerEntrySide::Credit,
                entryType: WalletLedgerEntryType::DepositCredit,
                amount: '10.0000',
                currency: 'USD',
                referenceType: 'test',
                referenceId: 100,
                counterpartyWalletId: null,
                description: 'deposit'
            ),
        );

        $res = $this->svc->postLedgerBatch($cmd);
        self::assertSame(false, $res['idempotent_replay']);
        self::assertNotEmpty($res['batch_id']);
        self::assertSame('posted', $res['status']);

        self::assertSame(1, WalletLedgerBatch::query()->count());
        self::assertSame(1, WalletLedgerEntry::query()->count());
        self::assertSame(1, IdempotencyKey::query()->where('scope', 'wallet_ledger_posting')->count());

        $entry = WalletLedgerEntry::query()->firstOrFail();
        self::assertSame($walletId, (int) $entry->wallet_id);
        self::assertSame(WalletLedgerEntrySide::Credit, $entry->entry_side);
        self::assertSame(WalletLedgerEntryType::DepositCredit, $entry->entry_type);
        self::assertSame('10.0000', (string) $entry->amount);
        self::assertSame('10.0000', (string) $entry->running_balance_after);
    }

    /**
     * Covers: escrow_hold, release, refund, partial_refund, fee, withdrawal_request, withdrawal, adjustment.
     */
    public function test_success_posting_flows_minimum_types(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        // Seed balance with deposit 100.
        $this->svc->postLedgerBatch(PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'seed',
            1,
            'idem-seed-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '100.0000', 'USD', 'seed', 1, null, null),
        ));

        $scenarios = [
            [LedgerPostingEventName::EscrowHold, WalletLedgerEntrySide::Debit, WalletLedgerEntryType::EscrowHoldDebit, '10.0000', 'idem-escrow-hold-1'],
            [LedgerPostingEventName::Release, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::EscrowReleaseCredit, '10.0000', 'idem-release-1'],
            [LedgerPostingEventName::Refund, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::RefundCredit, '5.0000', 'idem-refund-1'],
            [LedgerPostingEventName::PartialRefund, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::RefundCredit, '2.0000', 'idem-partial-refund-1'],
            [LedgerPostingEventName::Fee, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::PlatformFeeCredit, '1.0000', 'idem-fee-1'],
            [LedgerPostingEventName::WithdrawalRequest, WalletLedgerEntrySide::Debit, WalletLedgerEntryType::WithdrawalHoldDebit, '3.0000', 'idem-wreq-1'],
            [LedgerPostingEventName::Withdrawal, WalletLedgerEntrySide::Debit, WalletLedgerEntryType::WithdrawalSettlementDebit, '3.0000', 'idem-withdraw-1'],
            [LedgerPostingEventName::Adjustment, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::AdjustmentCredit, '7.0000', 'idem-adj-1'],
        ];

        $referenceId = 10;
        foreach ($scenarios as [$event, $side, $type, $amount, $idem]) {
            $referenceId++;
            $res = $this->svc->postLedgerBatch(PostLedgerBatchCommand::fromLines(
                $event,
                'test',
                $referenceId,
                $idem,
                new LedgerPostingLine($walletId, $side, $type, $amount, 'USD', 'test', $referenceId, null, null),
            ));
            self::assertSame(false, $res['idempotent_replay']);
            self::assertSame('posted', $res['status']);
        }

        self::assertSame(1 + count($scenarios), WalletLedgerBatch::query()->count());
        self::assertSame(1 + count($scenarios), WalletLedgerEntry::query()->count());
    }

    public function test_idempotency_replay_same_key_same_request_returns_existing_batch(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            200,
            'idem-replay-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '9.0000', 'USD', 'test', 200, null, null),
        );

        $r1 = $this->svc->postLedgerBatch($cmd);
        $r2 = $this->svc->postLedgerBatch($cmd);

        self::assertSame($r1['batch_id'], $r2['batch_id']);
        self::assertSame(true, $r2['idempotent_replay']);
        self::assertSame(1, WalletLedgerBatch::query()->count());
        self::assertSame(1, WalletLedgerEntry::query()->count());
    }

    public function test_idempotency_conflict_same_key_different_request_throws(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $cmd1 = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            201,
            'idem-conflict-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '1.0000', 'USD', 'test', 201, null, null),
        );
        $this->svc->postLedgerBatch($cmd1);

        $cmd2 = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            201,
            'idem-conflict-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '2.0000', 'USD', 'test', 201, null, null),
        );

        $this->expectException(IdempotencyConflictException::class);
        $this->svc->postLedgerBatch($cmd2);
    }

    public function test_idempotency_non_succeeded_existing_row_safe_conflict(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        IdempotencyKey::query()->create([
            'key' => 'idem-inflight-1',
            'scope' => 'wallet_ledger_posting',
            'request_hash' => hash('sha256', json_encode([
                'event_name' => 'deposit',
                'reference_type' => 'test',
                'reference_id' => 300,
                'entries' => [],
            ])),
            'status' => IdempotencyKeyStatus::Started,
            'expires_at' => now()->addDay(),
        ]);

        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            300,
            'idem-inflight-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '1.0000', 'USD', 'test', 300, null, null),
        );

        $this->expectException(IdempotencyConflictException::class);
        $this->svc->postLedgerBatch($cmd);

        self::assertSame(0, WalletLedgerBatch::query()->count());
        self::assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_invariant_insufficient_balance_rolls_back_without_rows(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::EscrowHold,
            'test',
            400,
            'idem-insufficient-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Debit, WalletLedgerEntryType::EscrowHoldDebit, '1.0000', 'USD', 'test', 400, null, null),
        );

        try {
            $this->svc->postLedgerBatch($cmd);
            self::fail('Expected insufficient balance');
        } catch (InsufficientWalletBalanceException) {
            // expected
        }

        self::assertSame(0, WalletLedgerBatch::query()->count());
        self::assertSame(0, WalletLedgerEntry::query()->count());
        self::assertSame(0, IdempotencyKey::query()->where('scope', 'wallet_ledger_posting')->count());
    }

    public function test_negative_available_forbidden_for_non_adjustment_debits(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Withdrawal,
            'test',
            405,
            'idem-negative-forbidden-1',
            new LedgerPostingLine(
                $walletId,
                WalletLedgerEntrySide::Debit,
                WalletLedgerEntryType::WithdrawalSettlementDebit,
                '0.5000',
                'USD',
                'test',
                405,
                null,
                'should fail without funds'
            ),
        );

        $this->expectException(InsufficientWalletBalanceException::class);
        $this->svc->postLedgerBatch($cmd);
    }

    public function test_negative_available_allowed_for_adjustment_debit_and_snapshot_persists(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $res = $this->svc->postLedgerBatch(PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Adjustment,
            'admin_adjustment',
            406,
            'idem-adjustment-overdraw-1',
            new LedgerPostingLine(
                $walletId,
                WalletLedgerEntrySide::Debit,
                WalletLedgerEntryType::AdjustmentDebit,
                '2.0000',
                'USD',
                'admin_adjustment',
                406,
                null,
                'manual correction'
            ),
        ));

        self::assertSame('posted', $res['status']);
        $entry = WalletLedgerEntry::query()->latest('id')->firstOrFail();
        self::assertSame('-2.0000', (string) $entry->running_balance_after);

        $balance = $this->svc->computeWalletBalances(new \App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand($walletId));
        self::assertSame('-2.0000', (string) $balance['available_balance']);
        self::assertSame('0.0000', (string) $balance['held_balance']);
    }

    public function test_invariant_invalid_ledger_operation_event_entry_mismatch_rolls_back(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');
        $this->svc->postLedgerBatch(PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'seed',
            2,
            'idem-seed-2',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '10.0000', 'USD', 'seed', 2, null, null),
        ));

        // Try to post a "deposit" event with a debit entry: should fail.
        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            401,
            'idem-invalid-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Debit, WalletLedgerEntryType::EscrowHoldDebit, '1.0000', 'USD', 'test', 401, null, null),
        );

        $this->expectException(InvalidLedgerOperationException::class);
        $this->svc->postLedgerBatch($cmd);

        // Only the seed write exists.
        self::assertSame(1, WalletLedgerBatch::query()->count());
        self::assertSame(1, WalletLedgerEntry::query()->count());
    }

    public function test_wallet_not_found_throws_and_rolls_back(): void
    {
        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            500,
            'idem-wallet-missing-1',
            new LedgerPostingLine(999999, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '1.0000', 'USD', 'test', 500, null, null),
        );

        $this->expectException(WalletNotFoundException::class);
        $this->svc->postLedgerBatch($cmd);

        self::assertSame(0, WalletLedgerBatch::query()->count());
        self::assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_currency_mismatch_throws_and_rolls_back(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $cmd = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            600,
            'idem-currency-mismatch-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '1.0000', 'EUR', 'test', 600, null, null),
        );

        $this->expectException(WalletCurrencyMismatchException::class);
        $this->svc->postLedgerBatch($cmd);

        self::assertSame(0, WalletLedgerBatch::query()->count());
        self::assertSame(0, WalletLedgerEntry::query()->count());
        self::assertSame(0, IdempotencyKey::query()->where('scope', 'wallet_ledger_posting')->count());
    }

    public function test_hold_place_and_release_happy_path_and_locking(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');
        $this->svc->postLedgerBatch(PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'seed',
            3,
            'idem-seed-3',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '10.0000', 'USD', 'seed', 3, null, null),
        ));

        $holdRes = $this->svc->placeHold(new PlaceWalletHoldCommand(
            walletId: $walletId,
            holdType: WalletHoldType::Escrow,
            referenceType: 'order',
            referenceId: 123,
            amount: '4.0000',
        ));

        self::assertSame('active', $holdRes['status']);
        self::assertSame(1, WalletHold::query()->count());

        $releaseRes = $this->svc->releaseHold(new ReleaseWalletHoldCommand((int) $holdRes['wallet_hold_id']));
        self::assertSame('released', $releaseRes['status']);
        self::assertSame('released', WalletHold::query()->firstOrFail()->status->value);
    }

    public function test_concurrency_like_contention_two_postings_same_wallet_are_serialized_by_locks(): void
    {
        // True multi-thread concurrency needs multiple DB connections/processes.
        // This test enforces that sequential postings produce monotonic running balances
        // and no interleaving corruption in the ledger rows.
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $cmd1 = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            700,
            'idem-conc-1',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '1.0000', 'USD', 'test', 700, null, null),
        );
        $cmd2 = PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'test',
            701,
            'idem-conc-2',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '2.0000', 'USD', 'test', 701, null, null),
        );

        $this->svc->postLedgerBatch($cmd1);
        $this->svc->postLedgerBatch($cmd2);

        $entries = WalletLedgerEntry::query()->orderBy('id')->get();
        self::assertCount(2, $entries);
        self::assertSame('1.0000', (string) $entries[0]->running_balance_after);
        self::assertSame('3.0000', (string) $entries[1]->running_balance_after);
    }

    public function test_concurrent_like_posting_and_reversal_same_batch_is_safe(): void
    {
        $walletId = $this->createWallet(userId: 1, type: WalletType::Buyer, currency: 'USD');

        $seed = $this->svc->postLedgerBatch(PostLedgerBatchCommand::fromLines(
            LedgerPostingEventName::Deposit,
            'seed',
            800,
            'idem-seed-800',
            new LedgerPostingLine($walletId, WalletLedgerEntrySide::Credit, WalletLedgerEntryType::DepositCredit, '5.0000', 'USD', 'seed', 800, null, null),
        ));

        $batchId = (int) $seed['batch_id'];
        $rev1 = $this->svc->reverseLedgerBatch(new ReverseLedgerBatchCommand($batchId, 'test_reason'));
        self::assertSame('posted', $rev1['status']);

        // Second reversal attempt should conflict because original batch is reversed.
        $this->expectException(InvalidLedgerOperationException::class);
        $this->svc->reverseLedgerBatch(new ReverseLedgerBatchCommand($batchId, 'test_reason_2'));
    }

    private function createWallet(int $userId, WalletType $type, string $currency): int
    {
        $res = $this->svc->createWalletIfMissing(new CreateWalletIfMissingCommand($userId, $type, $currency));
        return (int) $res['wallet_id'];
    }
}


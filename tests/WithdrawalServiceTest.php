<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Commands\Withdrawal\ApproveWithdrawalCommand;
use App\Domain\Commands\Withdrawal\RejectWithdrawalCommand;
use App\Domain\Commands\Withdrawal\RequestWithdrawalCommand;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\WalletHoldStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Enums\WithdrawalRequestStatus;
use App\Domain\Exceptions\WithdrawalValidationFailedException;
use App\Domain\Value\LedgerPostingLine;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletHold;
use App\Models\WithdrawalRequest;
use App\Services\WalletLedger\WalletLedgerService;
use App\Services\Withdrawal\WithdrawalService;
use Illuminate\Support\Str;

final class WithdrawalServiceTest extends TestCase
{
    private WalletLedgerService $wallet;
    private WithdrawalService $withdrawals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = new WalletLedgerService();
        $this->withdrawals = new WithdrawalService($this->wallet);
    }

    public function test_request_then_approve_moves_balance_and_sets_paid_out(): void
    {
        [$sellerProfileId, $walletId] = $this->seedSellerWithFundedWallet('500.0000');

        $r1 = $this->withdrawals->requestWithdrawal(new RequestWithdrawalCommand(
            sellerProfileId: $sellerProfileId,
            walletId: $walletId,
            amount: '120.0000',
            currency: 'USD',
            idempotencyKey: 'wd-req-'.Str::random(8),
        ));

        self::assertSame(WithdrawalRequestStatus::Requested->value, $r1['status']);
        $wr = WithdrawalRequest::query()->findOrFail($r1['withdrawal_request_id']);
        self::assertNotNull($wr->hold_id);

        $balAfterRequest = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($walletId));
        self::assertSame('380.0000', (string) $balAfterRequest['available_balance']); // 500 - 120 held

        $a1 = $this->withdrawals->approveWithdrawal(new ApproveWithdrawalCommand(
            withdrawalRequestId: $wr->id,
            reviewerUserId: 1,
            idempotencyKey: 'wd-appr-'.Str::random(8),
        ));
        self::assertSame(WithdrawalRequestStatus::PaidOut->value, $a1['status']);

        $balFinal = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($walletId));
        self::assertSame('380.0000', (string) $balFinal['available_balance']); // released hold then debited 120 net

        $hold = WalletHold::query()->findOrFail($wr->hold_id);
        self::assertSame(WalletHoldStatus::Released, $hold->status);
    }

    public function test_request_then_reject_restores_available_balance(): void
    {
        [$sellerProfileId, $walletId] = $this->seedSellerWithFundedWallet('200.0000');
        $reviewer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'reviewer-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);

        $r = $this->withdrawals->requestWithdrawal(new RequestWithdrawalCommand(
            sellerProfileId: $sellerProfileId,
            walletId: $walletId,
            amount: '40.0000',
            currency: 'USD',
            idempotencyKey: 'wd-req-rj-'.Str::random(8),
        ));
        $wrId = (int) $r['withdrawal_request_id'];

        $mid = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($walletId));
        self::assertSame('160.0000', (string) $mid['available_balance']);

        $this->withdrawals->rejectWithdrawal(new RejectWithdrawalCommand(
            withdrawalRequestId: $wrId,
            reviewerUserId: (int) $reviewer->id,
            idempotencyKey: 'wd-rj-'.Str::random(8),
            reason: 'KYC incomplete',
        ));

        $end = $this->wallet->computeWalletBalances(new ComputeWalletBalancesCommand($walletId));
        self::assertSame('200.0000', (string) $end['available_balance']);

        $wr = WithdrawalRequest::query()->findOrFail($wrId);
        self::assertSame(WithdrawalRequestStatus::Rejected, $wr->status);
        self::assertSame('KYC incomplete', (string) $wr->reject_reason);
    }

    public function test_request_withdrawal_idempotent_replay(): void
    {
        [$sellerProfileId, $walletId] = $this->seedSellerWithFundedWallet('300.0000');
        $key = 'wd-idem-'.Str::random(8);

        $first = $this->withdrawals->requestWithdrawal(new RequestWithdrawalCommand(
            $sellerProfileId,
            $walletId,
            '25.0000',
            'USD',
            $key,
        ));
        $second = $this->withdrawals->requestWithdrawal(new RequestWithdrawalCommand(
            $sellerProfileId,
            $walletId,
            '25.0000',
            'USD',
            $key,
        ));

        self::assertFalse($first['idempotent_replay']);
        self::assertTrue($second['idempotent_replay']);
        self::assertSame((int) $first['withdrawal_request_id'], (int) $second['withdrawal_request_id']);
    }

    public function test_second_approve_throws(): void
    {
        [$sellerProfileId, $walletId] = $this->seedSellerWithFundedWallet('80.0000');
        $r = $this->withdrawals->requestWithdrawal(new RequestWithdrawalCommand(
            $sellerProfileId,
            $walletId,
            '30.0000',
            'USD',
            'wd-dup-ap-'.Str::random(8),
        ));
        $wrId = (int) $r['withdrawal_request_id'];
        $this->withdrawals->approveWithdrawal(new ApproveWithdrawalCommand($wrId, 1, 'ap1-'.Str::random(6)));

        $this->expectException(WithdrawalValidationFailedException::class);
        try {
            $this->withdrawals->approveWithdrawal(new ApproveWithdrawalCommand($wrId, 1, 'ap2-'.Str::random(6)));
        } catch (WithdrawalValidationFailedException $e) {
            self::assertSame('withdrawal_already_paid_out', $e->reasonCode);
            throw $e;
        }
    }

    public function test_nonzero_fee_rejected(): void
    {
        [$sellerProfileId, $walletId] = $this->seedSellerWithFundedWallet('100.0000');

        $this->expectException(WithdrawalValidationFailedException::class);
        try {
            $this->withdrawals->requestWithdrawal(new RequestWithdrawalCommand(
                sellerProfileId: $sellerProfileId,
                walletId: $walletId,
                amount: '50.0000',
                currency: 'USD',
                idempotencyKey: 'wd-fee-'.Str::random(8),
                feeAmount: '1.0000',
            ));
        } catch (WithdrawalValidationFailedException $e) {
            self::assertSame('withdrawal_nonzero_fee_not_supported', $e->reasonCode);
            throw $e;
        }
    }

    /**
     * @return array{0: int, 1: int} seller_profile_id, wallet_id
     */
    private function seedSellerWithFundedWallet(string $depositAmount): array
    {
        $sellerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-wd-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Seller WD',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);

        $walletId = (int) $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $sellerUser->id,
            walletType: WalletType::Seller,
            currency: 'USD',
        ))['wallet_id'];

        $this->wallet->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'seed',
            referenceId: $seller->id,
            idempotencyKey: 'seed-wd-'.$seller->id.'-'.Str::random(6),
            entries: [
                new LedgerPostingLine(
                    walletId: $walletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: $depositAmount,
                    currency: 'USD',
                    referenceType: 'seed',
                    referenceId: $seller->id,
                    counterpartyWalletId: null,
                    description: 'seed_seller',
                ),
            ],
        ));

        return [(int) $seller->id, $walletId];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Ability;
use App\Auth\DomainGate;
use App\Auth\RoleCodes;
use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\EscrowState;
use App\Domain\Enums\DisputeResolutionOutcome;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletType;
use App\Domain\Enums\WithdrawalRequestStatus;
use App\Domain\Exceptions\DomainAuthorizationDeniedException;
use App\Models\DisputeCase;
use App\Models\DisputeDecision;
use App\Models\EscrowAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DomainGateAuthorizationTest extends TestCase
{
    private DomainGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new DomainGate();
    }

    public function test_buyer_may_open_dispute_only_on_own_paid_in_escrow_order(): void
    {
        [$buyer, $sellerUser, $order] = $this->seedOrderWithParticipants();

        $order->status = OrderStatus::PaidInEscrow;
        $order->save();

        self::assertTrue($this->gate->allows(Ability::OrderOpenDispute, $buyer, $order));
        self::assertFalse($this->gate->allows(Ability::OrderOpenDispute, $sellerUser, $order));

        $order->status = OrderStatus::Processing;
        $order->save();
        self::assertFalse($this->gate->allows(Ability::OrderOpenDispute, $buyer, $order));
    }

    public function test_payment_mutation_abilities_allow_buyer_and_admin_not_seller(): void
    {
        [$buyer, $sellerUser, $order] = $this->seedOrderWithParticipants();
        $order->status = OrderStatus::Draft;
        $order->save();

        self::assertTrue($this->gate->allows(Ability::OrderMarkPendingPayment, $buyer, $order));
        self::assertFalse($this->gate->allows(Ability::OrderMarkPendingPayment, $sellerUser, $order));
        self::assertTrue($this->gate->allows(Ability::OrderMarkPaid, $buyer, $order));
        self::assertFalse($this->gate->allows(Ability::OrderMarkPaid, $sellerUser, $order));

        $admin = $this->makeUser('admin-pay-mut');
        $this->assignRole($admin, RoleCodes::Admin);
        self::assertTrue($this->gate->allows(Ability::OrderMarkPendingPayment, $admin, $order));
        self::assertTrue($this->gate->allows(Ability::OrderMarkPaid, $admin, $order));

        $order->status = OrderStatus::PendingPayment;
        $order->save();
        self::assertTrue($this->gate->allows(Ability::OrderMarkPaid, $buyer, $order));
        self::assertFalse($this->gate->allows(Ability::OrderMarkPaid, $sellerUser, $order));
    }

    public function test_seller_cannot_resolve_dispute_even_when_on_order(): void
    {
        [$buyer, $sellerUser, $order] = $this->seedOrderWithParticipants();
        $case = $this->makeDisputeCase($order, $buyer, DisputeCaseStatus::UnderReview);

        self::assertFalse($this->gate->allows(Ability::DisputeResolve, $sellerUser, $case));
        self::assertFalse($this->gate->allows(Ability::DisputeResolve, $buyer, $case));
    }

    public function test_admin_and_adjudicator_may_resolve_dispute(): void
    {
        [$buyer, $sellerUser, $order] = $this->seedOrderWithParticipants();
        $admin = $this->makeUser('admin-user');
        $adjudicator = $this->makeUser('adj-user');
        $this->assignRole($admin, RoleCodes::Admin);
        $this->assignRole($adjudicator, RoleCodes::Adjudicator);

        $case = $this->makeDisputeCase($order, $buyer, DisputeCaseStatus::UnderReview);

        self::assertTrue($this->gate->allows(Ability::DisputeResolve, $admin, $case));
        self::assertTrue($this->gate->allows(Ability::DisputeResolve, $adjudicator, $case));
    }

    public function test_withdrawal_request_only_for_seller_profile_owner_not_admin(): void
    {
        $sellerUser = $this->makeUser('seller-w');
        $other = $this->makeUser('other-w');
        $admin = $this->makeUser('admin-w');
        $this->assignRole($admin, RoleCodes::Admin);

        $profile = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'S',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);

        $wallet = Wallet::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'wallet_type' => WalletType::Seller->value,
            'currency' => 'USD',
            'status' => 'active',
            'version' => 1,
        ]);

        self::assertTrue($this->gate->allows(Ability::WithdrawalRequest, $sellerUser, $profile, $wallet));
        self::assertFalse($this->gate->allows(Ability::WithdrawalRequest, $other, $profile, $wallet));
        self::assertFalse($this->gate->allows(Ability::WithdrawalRequest, $admin, $profile, $wallet));
    }

    public function test_withdrawal_view_seller_owner_or_admin(): void
    {
        $sellerUser = $this->makeUser('seller-view-w');
        $stranger = $this->makeUser('stranger-view-w');
        $admin = $this->makeUser('admin-view-w');
        $this->assignRole($admin, RoleCodes::Admin);

        $profile = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'S',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $wallet = Wallet::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'wallet_type' => WalletType::Seller->value,
            'currency' => 'USD',
            'status' => 'active',
            'version' => 1,
        ]);

        $wr = WithdrawalRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'idempotency_key' => 'w-view-'.Str::random(8),
            'seller_profile_id' => $profile->id,
            'wallet_id' => $wallet->id,
            'status' => WithdrawalRequestStatus::Requested,
            'requested_amount' => '10.0000',
            'fee_amount' => '0.0000',
            'net_payout_amount' => '10.0000',
            'currency' => 'USD',
            'hold_id' => null,
        ]);

        self::assertTrue($this->gate->allows(Ability::WithdrawalView, $sellerUser, $wr));
        self::assertTrue($this->gate->allows(Ability::WithdrawalView, $admin, $wr));
        self::assertFalse($this->gate->allows(Ability::WithdrawalView, $stranger, $wr));
    }

    public function test_withdrawal_approve_reject_admin_only_not_adjudicator(): void
    {
        $admin = $this->makeUser('admin-ww');
        $adj = $this->makeUser('adj-ww');
        $this->assignRole($admin, RoleCodes::Admin);
        $this->assignRole($adj, RoleCodes::Adjudicator);

        $sellerUser = $this->makeUser('seller-ww');
        $profile = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'S',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $wallet = Wallet::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'wallet_type' => WalletType::Seller->value,
            'currency' => 'USD',
            'status' => 'active',
            'version' => 1,
        ]);

        $wr = WithdrawalRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'idempotency_key' => 'w-'.Str::random(8),
            'seller_profile_id' => $profile->id,
            'wallet_id' => $wallet->id,
            'status' => WithdrawalRequestStatus::UnderReview,
            'requested_amount' => '10.0000',
            'fee_amount' => '0.0000',
            'net_payout_amount' => '10.0000',
            'currency' => 'USD',
            'hold_id' => null,
        ]);

        self::assertTrue($this->gate->allows(Ability::WithdrawalApprove, $admin, $wr));
        self::assertTrue($this->gate->allows(Ability::WithdrawalReject, $admin, $wr));
        self::assertFalse($this->gate->allows(Ability::WithdrawalApprove, $adj, $wr));
        self::assertFalse($this->gate->allows(Ability::WithdrawalReject, $adj, $wr));
    }

    public function test_order_escrow_visibility_for_buyer_seller_and_staff(): void
    {
        [$buyer, $sellerUser, $order] = $this->seedOrderWithParticipants();
        $escrow = EscrowAccount::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'state' => EscrowState::Held,
            'currency' => 'USD',
            'held_amount' => '10.0000',
            'released_amount' => '0.0000',
            'refunded_amount' => '0.0000',
            'version' => 1,
        ]);

        $stranger = $this->makeUser('stranger');
        $admin = $this->makeUser('admin-vis');
        $this->assignRole($admin, RoleCodes::Admin);

        self::assertTrue($this->gate->allows(Ability::OrderView, $buyer, $order));
        self::assertTrue($this->gate->allows(Ability::OrderView, $sellerUser, $order));
        self::assertFalse($this->gate->allows(Ability::OrderView, $stranger, $order));
        self::assertTrue($this->gate->allows(Ability::OrderView, $admin, $order));

        self::assertTrue($this->gate->allows(Ability::EscrowView, $buyer, $escrow));
        self::assertTrue($this->gate->allows(Ability::EscrowView, $sellerUser, $escrow));
        self::assertFalse($this->gate->allows(Ability::EscrowView, $stranger, $escrow));
        self::assertTrue($this->gate->allows(Ability::EscrowView, $admin, $escrow));
    }

    public function test_wallet_view_owner_or_staff(): void
    {
        $u = $this->makeUser('wallet-owner');
        $other = $this->makeUser('wallet-other');
        $admin = $this->makeUser('wallet-admin');
        $this->assignRole($admin, RoleCodes::Admin);

        $wallet = Wallet::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $u->id,
            'wallet_type' => WalletType::Buyer->value,
            'currency' => 'USD',
            'status' => 'active',
            'version' => 1,
        ]);

        self::assertTrue($this->gate->allows(Ability::WalletView, $u, $wallet));
        self::assertFalse($this->gate->allows(Ability::WalletView, $other, $wallet));
        self::assertTrue($this->gate->allows(Ability::WalletView, $admin, $wallet));
    }

    public function test_dispute_decision_visible_to_staff_only(): void
    {
        $buyer = $this->makeUser('buyer-dd');
        $seller = $this->makeUser('seller-dd');
        $admin = $this->makeUser('admin-dd');
        $this->assignRole($admin, RoleCodes::Admin);

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'O-'.Str::random(6),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::Disputed,
            'currency' => 'USD',
            'gross_amount' => '5.0000',
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => '5.0000',
            'placed_at' => now(),
        ]);

        $case = DisputeCase::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'order_item_id' => null,
            'opened_by_user_id' => $buyer->id,
            'status' => DisputeCaseStatus::Resolved,
            'resolution_outcome' => DisputeResolutionOutcome::BuyerWins,
            'opened_at' => now(),
            'resolved_at' => now(),
        ]);

        $decision = DisputeDecision::query()->create([
            'uuid' => (string) Str::uuid(),
            'dispute_case_id' => $case->id,
            'decided_by_user_id' => $admin->id,
            'outcome' => DisputeResolutionOutcome::BuyerWins,
            'buyer_amount' => '5.0000',
            'seller_amount' => '0.0000',
            'currency' => 'USD',
            'reason_code' => 'r',
            'notes' => 'n',
            'escrow_event_id' => null,
            'ledger_batch_id' => null,
            'decided_at' => now(),
        ]);

        self::assertFalse($this->gate->allows(Ability::DisputeDecisionView, $buyer, $decision));
        self::assertFalse($this->gate->allows(Ability::DisputeDecisionView, $seller, $decision));
        self::assertTrue($this->gate->allows(Ability::DisputeDecisionView, $admin, $decision));
    }

    public function test_authorize_throws_domain_exception(): void
    {
        [$buyer, $sellerUser, $order] = $this->seedOrderWithParticipants();
        $order->status = OrderStatus::PaidInEscrow;
        $order->save();

        $this->expectException(DomainAuthorizationDeniedException::class);
        $this->gate->authorize(Ability::OrderOpenDispute, $sellerUser, $order);
    }

    /**
     * @return array{0: User, 1: User, 2: Order}
     */
    private function seedOrderWithParticipants(): array
    {
        $buyer = $this->makeUser('buyer-'.Str::random(4));
        $sellerUser = $this->makeUser('seller-'.Str::random(4));

        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Seller',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'O-'.Str::random(8),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::Processing,
            'currency' => 'USD',
            'gross_amount' => '20.0000',
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => '20.0000',
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'seller_profile_id' => $seller->id,
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Item',
            'sku_snapshot' => 'SKU',
            'quantity' => 1,
            'unit_price_snapshot' => '20.0000',
            'line_total_snapshot' => '20.0000',
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ]);

        return [$buyer, $sellerUser, $order];
    }

    private function makeDisputeCase(Order $order, User $openedBy, DisputeCaseStatus $status): DisputeCase
    {
        return DisputeCase::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'order_item_id' => null,
            'opened_by_user_id' => $openedBy->id,
            'status' => $status,
            'resolution_outcome' => null,
            'opened_at' => now(),
            'resolved_at' => null,
        ]);
    }

    private function makeUser(string $emailPrefix): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => $emailPrefix.'@example.test',
            'password_hash' => 'x',
            'status' => 'active',
            'risk_level' => 'low',
        ]);
    }

    private function assignRole(User $user, string $roleCode): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => ucfirst($roleCode)],
        );

        $exists = UserRole::query()
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->exists();
        if ($exists) {
            return;
        }

        UserRole::query()->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'assigned_by' => null,
            'created_at' => now(),
        ]);
    }
}

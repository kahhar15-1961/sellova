<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Admin\AdminPermission;
use App\Auth\RoleCodes;
use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\DisputeResolutionOutcome;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Enums\WithdrawalRequestStatus;
use App\Domain\Value\LedgerPostingLine;
use App\Models\Category;
use App\Models\DisputeCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\Storefront;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WithdrawalRequest;
use App\Services\Escrow\EscrowService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Full local dataset for manual / E2E testing (truncate + insert).
 *
 * Password for every user: secret1234
 */
final class LocalAppSeeder
{
    public const PASSWORD_PLAIN = 'secret1234';

    /**
     * @var list<string>
     */
    private const TRUNCATE_TABLES = [
        'outbox_events', 'audit_logs', 'notifications', 'reviews', 'dispute_decisions', 'dispute_evidences',
        'dispute_cases', 'membership_subscriptions', 'payout_accounts', 'withdrawal_transactions',
        'withdrawal_requests', 'wallet_balance_snapshots', 'wallet_ledger_entries', 'wallet_ledger_batches',
        'wallet_holds', 'wallets', 'escrow_events', 'escrow_accounts', 'payment_webhook_events',
        'payment_transactions', 'payment_intents', 'idempotency_keys', 'order_state_transitions',
        'order_items', 'orders', 'commission_rules', 'membership_plans', 'cart_items', 'carts',
        'inventory_records', 'product_variants', 'products', 'categories', 'storefronts', 'kyc_documents',
        'kyc_verifications', 'seller_profiles', 'role_permissions', 'user_roles', 'permissions', 'roles',
        'user_auth_tokens', 'users',
    ];

    /** @var list<DisputeCaseStatus> */
    private const DISPUTE_STATUS_ROTATION = [
        DisputeCaseStatus::Opened,
        DisputeCaseStatus::EvidenceCollection,
        DisputeCaseStatus::UnderReview,
        DisputeCaseStatus::Escalated,
        DisputeCaseStatus::Resolved,
        DisputeCaseStatus::Resolved,
        DisputeCaseStatus::Opened,
        DisputeCaseStatus::UnderReview,
        DisputeCaseStatus::Escalated,
        DisputeCaseStatus::EvidenceCollection,
        DisputeCaseStatus::Resolved,
        DisputeCaseStatus::Opened,
    ];

    /** @var list<WithdrawalRequestStatus> */
    private const WITHDRAWAL_STATUS_ROTATION = [
        WithdrawalRequestStatus::Requested,
        WithdrawalRequestStatus::Requested,
        WithdrawalRequestStatus::UnderReview,
        WithdrawalRequestStatus::UnderReview,
        WithdrawalRequestStatus::Approved,
        WithdrawalRequestStatus::Approved,
        WithdrawalRequestStatus::Rejected,
        WithdrawalRequestStatus::Rejected,
        WithdrawalRequestStatus::PaidOut,
        WithdrawalRequestStatus::Requested,
        WithdrawalRequestStatus::UnderReview,
        WithdrawalRequestStatus::Approved,
    ];

    /**
     * Full reset + seed (preferred entry point for local / QA datasets).
     */
    public static function seedAll(): void
    {
        self::run(truncate: true);
    }

    public static function run(bool $truncate = true): void
    {
        if ($truncate) {
            self::truncateCoreTables();
        }

        $passwordHash = password_hash(self::PASSWORD_PLAIN, PASSWORD_DEFAULT);

        Role::query()->firstOrCreate(
            ['code' => RoleCodes::Adjudicator],
            ['name' => 'Adjudicator'],
        );
        self::ensureAdminPermissionMatrix();

        $admin = self::makeUser('admin@example.test', $passwordHash);
        self::ensureRole($admin, RoleCodes::Admin, 'Administrator');
        self::ensureRole($admin, RoleCodes::Adjudicator, 'Adjudicator');

        $sellers = [];
        foreach ([1, 2, 3] as $i) {
            $u = self::makeUser("seller{$i}@example.test", $passwordHash);
            $sellers[] = [
                'user' => $u,
                'profile' => SellerProfile::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $u->id,
                    'display_name' => "Seller {$i} Shop",
                    'legal_name' => "Seller {$i} LLC",
                    'country_code' => 'US',
                    'default_currency' => 'USD',
                    'verification_status' => 'unverified',
                    'store_status' => 'active',
                ]),
            ];
        }

        $buyers = [];
        foreach (range(1, 6) as $i) {
            $buyers[] = self::makeUser("buyer{$i}@example.test", $passwordHash);
        }

        $categories = [];
        foreach ([
            'Electronics',
            'Fashion',
            'Digital Products',
            'Home & Living',
            'Books & Stationery',
            'Sports & Outdoors',
            'Automotive',
            'Beauty & Health',
        ] as $idx => $name) {
            $categories[] = Category::query()->create([
                'parent_id' => null,
                'slug' => 'local-'.Str::slug($name).'-'.$idx,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $idx + 1,
            ]);
        }

        $storefronts = [];
        foreach ($sellers as $idx => $row) {
            /** @var SellerProfile $profile */
            $profile = $row['profile'];
            $storefronts[] = Storefront::query()->create([
                'uuid' => (string) Str::uuid(),
                'seller_profile_id' => $profile->id,
                'slug' => 'store-seller-'.($idx + 1),
                'title' => 'Storefront '.($idx + 1),
                'description' => 'Seeded storefront for seller '.($idx + 1),
                'policy_text' => null,
                'is_public' => true,
            ]);
        }

        /** @var list<list<Product>> $productsBySeller */
        $productsBySeller = [[], [], []];
        $products = [];
        $titles = [
            'Noise-Cancelling Headphones', 'USB-C Hub 7-in-1', 'Mechanical Keyboard', '4K Webcam',
            'Ergonomic Chair', 'Standing Desk', 'LED Desk Lamp', 'Wireless Mouse',
            'Portable SSD 1TB', 'Smart Watch', 'Bluetooth Speaker', 'Laptop Stand',
        ];
        for ($p = 0; $p < 12; $p++) {
            $si = $p % 3;
            $sf = $storefronts[$si];
            $sp = $sellers[$si]['profile'];
            $cat = $categories[$p % count($categories)];
            $price = number_format(19.99 + ($p * 7.5), 4, '.', '');
            $prod = Product::query()->create([
                'uuid' => (string) Str::uuid(),
                'seller_profile_id' => $sp->id,
                'storefront_id' => $sf->id,
                'category_id' => $cat->id,
                'product_type' => 'physical',
                'title' => $titles[$p],
                'description' => 'Demo listing. Image: https://placehold.co/640x480?text='.rawurlencode($titles[$p]),
                'base_price' => $price,
                'currency' => 'USD',
                'status' => 'published',
                'published_at' => now()->subHours(12 - $p),
            ]);
            $products[] = $prod;
            $productsBySeller[$si][] = $prod;
        }

        $wallet = new WalletLedgerService();
        $escrow = new EscrowService($wallet);

        foreach (array_merge([$admin], array_column($sellers, 'user'), $buyers) as $u) {
            $wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
                userId: $u->id,
                walletType: WalletType::Buyer,
                currency: 'USD',
            ));
        }
        foreach ($sellers as $row) {
            $wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
                userId: $row['user']->id,
                walletType: WalletType::Seller,
                currency: 'USD',
            ));
        }

        foreach ($buyers as $b) {
            $wid = (int) $wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
                userId: $b->id,
                walletType: WalletType::Buyer,
                currency: 'USD',
            ))['wallet_id'];
            $wallet->postLedgerBatch(new PostLedgerBatchCommand(
                eventName: LedgerPostingEventName::Deposit,
                referenceType: 'local_seed',
                referenceId: $b->id,
                idempotencyKey: 'local-seed-buyer-deposit-'.$b->id,
                entries: [
                    new LedgerPostingLine(
                        walletId: $wid,
                        entrySide: WalletLedgerEntrySide::Credit,
                        entryType: WalletLedgerEntryType::DepositCredit,
                        amount: '25000.0000',
                        currency: 'USD',
                        referenceType: 'local_seed',
                        referenceId: $b->id,
                        counterpartyWalletId: null,
                        description: 'seed_buyer_balance',
                    ),
                ],
            ));
        }
        foreach ($sellers as $row) {
            $wid = (int) $wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
                userId: $row['user']->id,
                walletType: WalletType::Seller,
                currency: 'USD',
            ))['wallet_id'];
            $wallet->postLedgerBatch(new PostLedgerBatchCommand(
                eventName: LedgerPostingEventName::Deposit,
                referenceType: 'local_seed',
                referenceId: $row['profile']->id,
                idempotencyKey: 'local-seed-seller-deposit-'.$row['profile']->id,
                entries: [
                    new LedgerPostingLine(
                        walletId: $wid,
                        entrySide: WalletLedgerEntrySide::Credit,
                        entryType: WalletLedgerEntryType::DepositCredit,
                        amount: '100000.0000',
                        currency: 'USD',
                        referenceType: 'local_seed',
                        referenceId: $row['profile']->id,
                        counterpartyWalletId: null,
                        description: 'seed_seller_balance',
                    ),
                ],
            ));
        }

        $orders = [];
        $orderSpecs = [
            ['n' => 'LOCAL-ORD-001', 'st' => OrderStatus::Draft, 'buyer' => 0, 'amt' => '45.0000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-002', 'st' => OrderStatus::Draft, 'buyer' => 1, 'amt' => '30.0000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-003', 'st' => OrderStatus::PendingPayment, 'buyer' => 2, 'amt' => '55.0000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-004', 'st' => OrderStatus::PendingPayment, 'buyer' => 3, 'amt' => '22.5000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-005', 'st' => OrderStatus::Paid, 'buyer' => 4, 'amt' => '90.0000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-006', 'st' => OrderStatus::Paid, 'buyer' => 5, 'amt' => '18.0000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-007', 'st' => OrderStatus::PaidInEscrow, 'buyer' => 0, 'amt' => '120.0000', 'escrow' => true],
            ['n' => 'LOCAL-ORD-008', 'st' => OrderStatus::PaidInEscrow, 'buyer' => 1, 'amt' => '200.0000', 'escrow' => true],
            ['n' => 'LOCAL-ORD-009', 'st' => OrderStatus::PaidInEscrow, 'buyer' => 2, 'amt' => '75.0000', 'escrow' => true],
            ['n' => 'LOCAL-ORD-010', 'st' => OrderStatus::Completed, 'buyer' => 3, 'amt' => '64.0000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-011', 'st' => OrderStatus::Completed, 'buyer' => 4, 'amt' => '33.0000', 'escrow' => false],
            ['n' => 'LOCAL-ORD-012', 'st' => OrderStatus::Completed, 'buyer' => 5, 'amt' => '48.0000', 'escrow' => false],
        ];

        foreach ($orderSpecs as $idx => $spec) {
            /** @var OrderStatus $st */
            $st = $spec['st'];
            $buyerUser = $buyers[$spec['buyer']];
            $si = $idx % 3;
            $sellerProfile = $sellers[$si]['profile'];
            $productForLine = $productsBySeller[$si][$idx % max(1, count($productsBySeller[$si]))];
            $placed = in_array($st, [OrderStatus::Paid, OrderStatus::PaidInEscrow, OrderStatus::Completed, OrderStatus::PendingPayment], true) ? now() : null;
            $completed = $st === OrderStatus::Completed ? now() : null;
            $o = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_number' => $spec['n'],
                'buyer_user_id' => $buyerUser->id,
                'status' => $st,
                'currency' => 'USD',
                'gross_amount' => $spec['amt'],
                'discount_amount' => '0.0000',
                'fee_amount' => '0.0000',
                'net_amount' => $spec['amt'],
                'placed_at' => $placed,
                'completed_at' => $completed,
            ]);
            OrderItem::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $o->id,
                'seller_profile_id' => $sellerProfile->id,
                'product_id' => $productForLine->id,
                'product_variant_id' => null,
                'product_type_snapshot' => 'physical',
                'title_snapshot' => 'Seeded line item',
                'sku_snapshot' => 'SKU-'.$o->id,
                'quantity' => 1,
                'unit_price_snapshot' => $spec['amt'],
                'line_total_snapshot' => $spec['amt'],
                'commission_rule_snapshot_json' => [],
                'delivery_state' => 'not_started',
            ]);
            $orders[] = ['order' => $o, 'escrow' => $spec['escrow'], 'amount' => $spec['amt']];
        }

        foreach ($orders as $row) {
            if (! $row['escrow']) {
                continue;
            }
            /** @var Order $o */
            $o = $row['order'];
            $create = $escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
                orderId: $o->id,
                currency: 'USD',
                heldAmount: (string) $o->net_amount,
                idempotencyKey: 'local-seed-escrow-create-'.$o->id,
            ));
            $escrow->holdEscrow(new HoldEscrowCommand(
                escrowAccountId: (int) $create['escrow_account_id'],
                idempotencyKey: 'local-seed-escrow-hold-'.$o->id,
            ));
        }

        $disputeOrders = [];
        for ($i = 0; $i < 12; $i++) {
            $buyerUser = $buyers[$i % 6];
            $si = $i % 3;
            $sellerProfile = $sellers[$si]['profile'];
            $productForLine = $productsBySeller[$si][$i % max(1, count($productsBySeller[$si]))];
            $o = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_number' => 'LOCAL-DISP-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'buyer_user_id' => $buyerUser->id,
                'status' => OrderStatus::Completed,
                'currency' => 'USD',
                'gross_amount' => '40.0000',
                'discount_amount' => '0.0000',
                'fee_amount' => '0.0000',
                'net_amount' => '40.0000',
                'placed_at' => now()->subDays(5),
                'completed_at' => now()->subDays(1),
            ]);
            $item = OrderItem::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $o->id,
                'seller_profile_id' => $sellerProfile->id,
                'product_id' => $productForLine->id,
                'product_variant_id' => null,
                'product_type_snapshot' => 'physical',
                'title_snapshot' => 'Dispute carrier item',
                'sku_snapshot' => 'DISP-'.$o->id,
                'quantity' => 1,
                'unit_price_snapshot' => '40.0000',
                'line_total_snapshot' => '40.0000',
                'commission_rule_snapshot_json' => [],
                'delivery_state' => 'delivered',
            ]);
            $disputeOrders[] = ['order' => $o, 'item' => $item, 'buyer' => $buyerUser];
        }

        foreach ($disputeOrders as $i => $d) {
            $status = self::DISPUTE_STATUS_ROTATION[$i % count(self::DISPUTE_STATUS_ROTATION)];
            $resolvedAt = $status === DisputeCaseStatus::Resolved ? now()->subHours(2) : null;
            $outcome = $status === DisputeCaseStatus::Resolved
                ? ($i % 2 === 0 ? DisputeResolutionOutcome::BuyerWins : DisputeResolutionOutcome::SellerWins)
                : null;
            DisputeCase::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $d['order']->id,
                'order_item_id' => $d['item']->id,
                'opened_by_user_id' => $d['buyer']->id,
                'status' => $status,
                'resolution_outcome' => $outcome,
                'opened_at' => now()->subDays(3),
                'resolved_at' => $resolvedAt,
                'resolution_notes' => $status === DisputeCaseStatus::Resolved ? 'Seeded resolution' : null,
            ]);
        }

        $sellerWallets = [];
        foreach ($sellers as $row) {
            $sellerWallets[] = (int) $wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
                userId: $row['user']->id,
                walletType: WalletType::Seller,
                currency: 'USD',
            ))['wallet_id'];
        }

        for ($w = 0; $w < 12; $w++) {
            $sp = $sellers[$w % 3]['profile'];
            $walletId = $sellerWallets[$w % 3];
            $st = self::WITHDRAWAL_STATUS_ROTATION[$w % count(self::WITHDRAWAL_STATUS_ROTATION)];
            $amt = number_format(50 + $w * 5, 4, '.', '');
            $reviewedBy = in_array($st, [
                WithdrawalRequestStatus::Approved,
                WithdrawalRequestStatus::Rejected,
                WithdrawalRequestStatus::PaidOut,
                WithdrawalRequestStatus::UnderReview,
            ], true) ? $admin->id : null;
            $reviewedAt = $reviewedBy !== null ? now()->subHours(1) : null;
            WithdrawalRequest::query()->create([
                'uuid' => (string) Str::uuid(),
                'idempotency_key' => 'local-seed-wr-'.str_pad((string) ($w + 1), 4, '0', STR_PAD_LEFT),
                'seller_profile_id' => $sp->id,
                'wallet_id' => $walletId,
                'status' => $st,
                'requested_amount' => $amt,
                'fee_amount' => '0.0000',
                'net_payout_amount' => $amt,
                'currency' => 'USD',
                'hold_id' => null,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
                'reject_reason' => $st === WithdrawalRequestStatus::Rejected ? 'Seeded rejection (demo).' : null,
            ]);
        }
    }

    private static function truncateCoreTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TRUNCATE_TABLES as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private static function makeUser(string $email, string $passwordHash): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => $email,
            'phone' => null,
            'password_hash' => $passwordHash,
            'status' => 'active',
            'risk_level' => 'low',
        ]);
    }

    private static function ensureRole(User $user, string $roleCode, string $roleName): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => $roleName],
        );
        UserRole::query()->firstOrCreate(
            ['user_id' => $user->id, 'role_id' => $role->id],
            ['assigned_by' => null],
        );
    }

    private static function ensureAdminPermissionMatrix(): void
    {
        $adminRole = Role::query()->firstOrCreate(
            ['code' => RoleCodes::Admin],
            ['name' => 'Administrator'],
        );
        $adjudicatorRole = Role::query()->firstOrCreate(
            ['code' => RoleCodes::Adjudicator],
            ['name' => 'Adjudicator'],
        );

        foreach (AdminPermission::all() as $code) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $code],
                ['name' => ucfirst(str_replace(['admin.', '.'], ['', ' '], $code))],
            );
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $adminRole->id,
                'permission_id' => $permission->id,
                'created_at' => now(),
            ]);
        }

        foreach ([AdminPermission::ACCESS, AdminPermission::DISPUTES_VIEW, AdminPermission::DISPUTES_RESOLVE] as $code) {
            $permission = Permission::query()->where('code', $code)->first();
            if ($permission !== null) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $adjudicatorRole->id,
                    'permission_id' => $permission->id,
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * @return array{emails: list<string>, password: string}
     */
    public static function credentialsSummary(): array
    {
        $emails = array_merge(
            ['admin@example.test — roles: admin, adjudicator'],
            ['seller1@example.test — seller (storefront + catalog)'],
            ['seller2@example.test — seller (storefront + catalog)'],
            ['seller3@example.test — seller (storefront + catalog)'],
            array_map(static fn (int $i): string => "buyer{$i}@example.test — buyer", range(1, 6)),
        );

        return [
            'emails' => $emails,
            'password' => self::PASSWORD_PLAIN,
        ];
    }
}

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
use App\Models\AdminEscalationPolicy;
use App\Models\AdminOnCallRotation;
use App\Models\BuyerReview;
use App\Models\DisputeCase;
use App\Models\InventoryRecord;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Permission;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\ShippingMethod;
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
        'outbox_events', 'audit_logs', 'notifications', 'profile_view_logs', 'trust_score_snapshots', 'review_reports',
        'review_ratings', 'marketplace_reviews', 'buyer_profiles', 'buyer_reviews', 'review_helpful_votes', 'reviews', 'dispute_decisions', 'dispute_evidences',
        'dispute_cases', 'membership_subscriptions', 'payout_accounts', 'withdrawal_transactions',
        'withdrawal_requests', 'wallet_top_up_requests', 'wallet_balance_snapshots', 'wallet_ledger_entries', 'wallet_ledger_batches',
        'wallet_holds', 'wallets', 'escrow_events', 'escrow_accounts', 'payment_webhook_events',
        'payment_transactions', 'payment_intents', 'idempotency_keys', 'order_state_transitions',
        'order_items', 'orders', 'commission_rules', 'membership_plans', 'cart_items', 'carts',
        'inventory_records', 'product_variants', 'products', 'seller_shipping_methods', 'shipping_methods',
        'seller_category_requests', 'categories', 'storefronts', 'kyc_documents', 'kyc_verifications',
        'seller_profiles', 'promotions', 'role_permissions', 'user_roles', 'permissions', 'roles', 'user_auth_tokens',
        'users',
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
        self::ensureRole($admin, RoleCodes::KycReviewer, 'KYC Reviewer');

        AdminEscalationPolicy::query()->updateOrCreate(
            ['queue_code' => 'seller_kyc'],
            [
                'default_severity' => 'medium',
                'auto_assign_on_call' => true,
                'on_call_role_code' => RoleCodes::KycReviewer,
                'ack_sla_minutes' => 15,
                'resolve_sla_minutes' => 1440,
                'is_enabled' => true,
            ],
        );

        foreach (range(0, 6) as $weekday) {
            AdminOnCallRotation::query()->create([
                'role_code' => RoleCodes::KycReviewer,
                'user_id' => $admin->id,
                'weekday' => $weekday,
                'start_hour' => 0,
                'end_hour' => 23,
                'priority' => 1,
                'is_active' => true,
            ]);
        }

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

        $categories = self::seedMarketplaceCategories();
        self::seedShippingMethods();

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
        foreach (self::marketplaceProductCatalog() as $index => $spec) {
            $si = $index % 3;
            /** @var SellerProfile $sellerProfile */
            $sellerProfile = $sellers[$si]['profile'];
            /** @var Storefront $storefront */
            $storefront = $storefronts[$si];
            $category = self::findCategoryByName($categories, (string) $spec['category']);
            $title = (string) $spec['title'];
            $primaryImage = 'https://placehold.co/960x720?text='.rawurlencode($title);
            $gallery = [
                $primaryImage,
                'https://placehold.co/960x720/f8fafc/111827?text='.rawurlencode($title.' View 2'),
                'https://placehold.co/960x720/e0e7ff/111827?text='.rawurlencode($title.' View 3'),
            ];

            $product = Product::query()->create([
                'uuid' => (string) Str::uuid(),
                'seller_profile_id' => $sellerProfile->id,
                'storefront_id' => $storefront->id,
                'category_id' => $category->id,
                'product_type' => (string) $spec['product_type'],
                'title' => $title,
                'description' => (string) $spec['description'],
                'base_price' => (string) $spec['price'],
                'discount_percentage' => (string) ($spec['discount_percentage'] ?? '0.00'),
                'discount_label' => $spec['discount_label'] ?? null,
                'currency' => 'USD',
                'image_url' => $primaryImage,
                'images_json' => $gallery,
                'attributes_json' => $spec['attributes'],
                'status' => 'published',
                'published_at' => now()->subHours(max(1, 36 - $index)),
            ]);

            InventoryRecord::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'stock_on_hand' => (int) ($spec['stock_on_hand'] ?? 0),
                'stock_reserved' => (int) ($spec['stock_reserved'] ?? 0),
                'stock_sold' => (int) ($spec['stock_sold'] ?? 0),
                'version' => 1,
            ]);

            $products[] = $product;
            $productsBySeller[$si][] = $product;
        }

        Promotion::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'FLASH25',
            'title' => 'Flash Deals',
            'description' => 'Limited-time catalog campaign for the storefront flash deals section.',
            'badge' => 'Flash Deal',
            'campaign_type' => 'catalog',
            'scope_type' => 'products',
            'target_product_ids' => array_map(static fn (Product $product): int => (int) $product->id, array_slice($products, 0, 5)),
            'target_seller_profile_ids' => [],
            'target_category_ids' => [],
            'target_product_types' => [],
            'currency' => 'USD',
            'discount_type' => 'percentage',
            'discount_value' => '0.2500',
            'min_spend' => '0.0000',
            'max_discount_amount' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->endOfDay(),
            'daily_start_time' => null,
            'daily_end_time' => null,
            'usage_limit' => null,
            'priority' => 500,
            'marketing_channel' => 'homepage',
            'used_count' => 0,
            'is_active' => true,
        ]);

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
            $productTypeSnapshot = self::orderProductTypeSnapshot($productForLine);
            $placed = in_array($st, [OrderStatus::Paid, OrderStatus::PaidInEscrow, OrderStatus::Completed, OrderStatus::PendingPayment], true) ? now() : null;
            $completed = $st === OrderStatus::Completed ? now() : null;
            $o = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_number' => $spec['n'],
                'buyer_user_id' => $buyerUser->id,
                'seller_user_id' => $sellers[$si]['user']->id,
                'primary_product_id' => $productForLine->id,
                'product_type' => $productTypeSnapshot,
                'status' => $st,
                'currency' => 'USD',
                'gross_amount' => $spec['amt'],
                'discount_amount' => '0.0000',
                'fee_amount' => '0.0000',
                'net_amount' => $spec['amt'],
                'placed_at' => $placed,
                'completed_at' => $completed,
            ]);
            $item = OrderItem::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $o->id,
                'seller_profile_id' => $sellerProfile->id,
                'product_id' => $productForLine->id,
                'product_variant_id' => null,
                'product_type_snapshot' => $productTypeSnapshot,
                'title_snapshot' => (string) $productForLine->title,
                'sku_snapshot' => 'SKU-'.$o->id,
                'quantity' => 1,
                'unit_price_snapshot' => $spec['amt'],
                'line_total_snapshot' => $spec['amt'],
                'commission_rule_snapshot_json' => [],
                'delivery_state' => 'not_started',
            ]);
            if ($st === OrderStatus::Completed) {
                Review::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'order_item_id' => $item->id,
                    'buyer_user_id' => $buyerUser->id,
                    'seller_profile_id' => $sellerProfile->id,
                    'product_id' => $productForLine->id,
                    'rating' => [5, 4, 5][$idx % 3],
                    'comment' => 'Verified purchase review from the local seed dataset.',
                    'status' => 'visible',
                ]);
                BuyerReview::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'order_id' => $o->id,
                    'seller_user_id' => $sellers[$si]['user']->id,
                    'seller_profile_id' => $sellerProfile->id,
                    'buyer_user_id' => $buyerUser->id,
                    'rating' => [5, 4, 5][$idx % 3],
                    'comment' => [
                        'Prompt confirmation and clear communication throughout the order.',
                        'Smooth buyer handoff with fast review after delivery.',
                        'Professional buyer, easy to coordinate delivery details.',
                    ][$idx % 3],
                    'status' => 'visible',
                ]);
            }
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
            $productTypeSnapshot = self::orderProductTypeSnapshot($productForLine);
            $o = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_number' => 'LOCAL-DISP-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'buyer_user_id' => $buyerUser->id,
                'seller_user_id' => $sellers[$si]['user']->id,
                'primary_product_id' => $productForLine->id,
                'product_type' => $productTypeSnapshot,
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
                'product_type_snapshot' => $productTypeSnapshot,
                'title_snapshot' => (string) $productForLine->title,
                'sku_snapshot' => 'DISP-'.$o->id,
                'quantity' => 1,
                'unit_price_snapshot' => '40.0000',
                'line_total_snapshot' => '40.0000',
                'commission_rule_snapshot_json' => [],
                'delivery_state' => 'delivered',
            ]);
            Review::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_item_id' => $item->id,
                'buyer_user_id' => $buyerUser->id,
                'seller_profile_id' => $sellerProfile->id,
                'product_id' => $productForLine->id,
                'rating' => [5, 5, 4, 3, 4, 5][$i % 6],
                'comment' => 'Marketplace quality signal generated from a completed seed order.',
                'status' => 'visible',
            ]);
            BuyerReview::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $o->id,
                'seller_user_id' => $sellers[$si]['user']->id,
                'seller_profile_id' => $sellerProfile->id,
                'buyer_user_id' => $buyerUser->id,
                'rating' => [5, 4, 4, 3, 5, 4][$i % 6],
                'comment' => [
                    'Responsive buyer with clear requirements.',
                    'Buyer reviewed delivery quickly and kept communication focused.',
                    'Good transaction, no operational issues.',
                    'Some clarification was needed, but the order closed properly.',
                    'Excellent buyer experience from checkout to completion.',
                    'Reliable buyer with timely responses.',
                ][$i % 6],
                'status' => 'visible',
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

        PromotionSeeder::seedDefaults();
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

    /**
     * @return list<Category>
     */
    private static function seedMarketplaceCategories(): array
    {
        $tree = [
            [
                'name' => 'Electronics',
                'description' => 'Phones, laptops, accessories, gadgets, and consumer electronics.',
                'children' => ['Mobile Phones', 'Laptops & Computers', 'Audio & Headphones', 'Cameras', 'Accessories'],
            ],
            [
                'name' => 'Fashion',
                'description' => 'Men, women, kids, footwear, bags, and personal style products.',
                'children' => ['Men Clothing', 'Women Clothing', 'Shoes', 'Bags & Wallets', 'Watches'],
            ],
            [
                'name' => 'Digital Products',
                'description' => 'Instant-delivery accounts, subscriptions, software, game items, and license keys.',
                'children' => ['Game Accounts', 'Software Licenses', 'Subscription Packages', 'Gift Cards', 'Digital Courses'],
            ],
            [
                'name' => 'Home & Living',
                'description' => 'Furniture, kitchen items, decor, lighting, and home improvement essentials.',
                'children' => ['Furniture', 'Kitchen & Dining', 'Home Decor', 'Lighting', 'Bedding'],
            ],
            [
                'name' => 'Books & Stationery',
                'description' => 'Books, office supplies, school essentials, and creative stationery.',
                'children' => ['Books', 'Office Supplies', 'School Supplies', 'Art Supplies'],
            ],
            [
                'name' => 'Sports & Outdoors',
                'description' => 'Sports equipment, fitness gear, outdoor tools, and travel essentials.',
                'children' => ['Fitness Equipment', 'Outdoor Gear', 'Sportswear', 'Travel Accessories'],
            ],
            [
                'name' => 'Automotive',
                'description' => 'Vehicle accessories, care products, parts, and riding essentials.',
                'children' => ['Car Accessories', 'Motorbike Accessories', 'Vehicle Care', 'Parts'],
            ],
            [
                'name' => 'Beauty & Health',
                'description' => 'Skincare, grooming, cosmetics, wellness, and personal-care items.',
                'children' => ['Skincare', 'Hair Care', 'Makeup', 'Personal Care', 'Health Devices'],
            ],
            [
                'name' => 'Baby & Kids',
                'description' => 'Baby care, toys, kids fashion, nursery goods, and learning essentials.',
                'children' => ['Baby Care', 'Toys & Games', 'Kids Clothing', 'Nursery', 'School Essentials'],
            ],
            [
                'name' => 'Grocery & Food',
                'description' => 'Pantry staples, snacks, drinks, organic foods, and household groceries.',
                'children' => ['Pantry Staples', 'Snacks', 'Beverages', 'Organic Food', 'Household Supplies'],
            ],
            [
                'name' => 'Pet Supplies',
                'description' => 'Food, care, toys, grooming, and accessories for pets.',
                'children' => ['Pet Food', 'Pet Grooming', 'Pet Toys', 'Beds & Carriers', 'Aquarium Supplies'],
            ],
            [
                'name' => 'Industrial & Tools',
                'description' => 'Tools, safety gear, electrical supplies, and business equipment.',
                'children' => ['Power Tools', 'Hand Tools', 'Safety Equipment', 'Electrical Supplies', 'Office Equipment'],
            ],
            [
                'name' => 'Jewelry & Accessories',
                'description' => 'Jewelry, eyewear, fashion accessories, and premium personal items.',
                'children' => ['Fine Jewelry', 'Fashion Jewelry', 'Eyewear', 'Belts', 'Scarves'],
            ],
            [
                'name' => 'Travel & Luggage',
                'description' => 'Bags, luggage, travel organizers, and trip-ready accessories.',
                'children' => ['Suitcases', 'Backpacks', 'Travel Organizers', 'Travel Electronics', 'Outdoor Travel'],
            ],
            [
                'name' => 'Gaming',
                'description' => 'Consoles, accessories, games, collectibles, and gaming services.',
                'children' => ['Consoles', 'Games', 'Controllers', 'Gaming Chairs', 'Collectibles'],
            ],
            [
                'name' => 'Music & Instruments',
                'description' => 'Instruments, audio gear, studio equipment, and learning accessories.',
                'children' => ['Guitars', 'Keyboards', 'Drums', 'Studio Equipment', 'Instrument Accessories'],
            ],
        ];

        $usableCategories = [];
        foreach ($tree as $rootIndex => $rootSpec) {
            $root = self::upsertCategory(
                name: $rootSpec['name'],
                parentId: null,
                sortOrder: ($rootIndex + 1) * 100,
                description: $rootSpec['description'],
            );
            $usableCategories[] = $root;

            foreach ($rootSpec['children'] as $childIndex => $childName) {
                $usableCategories[] = self::upsertCategory(
                    name: $childName,
                    parentId: (int) $root->id,
                    sortOrder: (($rootIndex + 1) * 100) + $childIndex + 1,
                    description: $childName.' under '.$rootSpec['name'].'.',
                );
            }
        }

        return $usableCategories;
    }

    private static function upsertCategory(string $name, ?int $parentId, int $sortOrder, ?string $description = null): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'parent_id' => $parentId,
                'name' => $name,
                'description' => $description,
                'image_url' => null,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }

    private static function seedShippingMethods(): void
    {
        foreach ([
            ['inside_dhaka', 'Inside Dhaka', '60.00', 'Same day', 10],
            ['outside_dhaka', 'Outside Dhaka', '120.00', '1-2 Business Days', 20],
            ['inside_rangpur', 'Inside Rangpur', '90.00', '1-2 Business Days', 30],
            ['inside_chattogram', 'Inside Chattogram', '90.00', '1-2 Business Days', 40],
            ['outside_city', 'Outside City / Regional', '140.00', '3-5 Business Days', 50],
            ['instant_digital', 'Instant Digital Delivery', '0.00', 'Instant', 60],
        ] as [$code, $name, $fee, $processing, $sortOrder]) {
            $method = ShippingMethod::query()->firstOrNew(['code' => $code]);
            if (! $method->exists) {
                $method->uuid = (string) Str::uuid();
            }
            $method->fill([
                'name' => $name,
                'suggested_fee' => $fee,
                'processing_time_label' => $processing,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ])->save();
        }
    }

    /**
     * @return list<array{
     *   title:string,
     *   category:string,
     *   product_type:string,
     *   price:string,
     *   discount_percentage:string,
     *   discount_label:?string,
     *   description:string,
     *   stock_on_hand:int,
     *   stock_reserved:int,
     *   stock_sold:int,
     *   attributes:array<string,mixed>
     * }>
     */
    private static function marketplaceProductCatalog(): array
    {
        return [
            [
                'title' => 'Noise-Cancelling Headphones Pro X',
                'category' => 'Audio & Headphones',
                'product_type' => 'physical',
                'price' => '189.9900',
                'discount_percentage' => '10.00',
                'discount_label' => 'Launch price',
                'description' => 'Premium over-ear headphones with active noise cancellation, 32-hour battery life, and travel case included.',
                'stock_on_hand' => 48,
                'stock_reserved' => 4,
                'stock_sold' => 112,
                'attributes' => ['brand' => 'Auralab', 'condition' => 'New', 'product_location' => 'Dhaka', 'warranty_status' => '1 year warranty', 'tags' => ['audio', 'premium', 'wireless']],
            ],
            [
                'title' => 'Mechanical Keyboard TKL RGB',
                'category' => 'Accessories',
                'product_type' => 'physical',
                'price' => '94.5000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Compact hot-swappable mechanical keyboard with linear switches and full RGB profiles.',
                'stock_on_hand' => 64,
                'stock_reserved' => 6,
                'stock_sold' => 87,
                'attributes' => ['brand' => 'KeyForge', 'condition' => 'New', 'product_location' => 'Chattogram', 'warranty_status' => '6 months warranty', 'tags' => ['keyboard', 'gaming', 'rgb']],
            ],
            [
                'title' => 'Ergonomic Executive Chair',
                'category' => 'Furniture',
                'product_type' => 'physical',
                'price' => '249.0000',
                'discount_percentage' => '12.00',
                'discount_label' => 'Warehouse deal',
                'description' => 'Mesh-backed ergonomic chair with lumbar support, adjustable armrests, and premium wheelbase.',
                'stock_on_hand' => 18,
                'stock_reserved' => 2,
                'stock_sold' => 34,
                'attributes' => ['brand' => 'OfficePeak', 'condition' => 'New', 'product_location' => 'Rangpur', 'warranty_status' => '2 year warranty', 'tags' => ['office', 'chair', 'ergonomic']],
            ],
            [
                'title' => 'Portable SSD 1TB Gen4',
                'category' => 'Laptops & Computers',
                'product_type' => 'physical',
                'price' => '129.0000',
                'discount_percentage' => '8.00',
                'discount_label' => 'Fast storage',
                'description' => 'Ultra-fast external SSD with USB-C connectivity, shock resistance, and encrypted backup utility.',
                'stock_on_hand' => 37,
                'stock_reserved' => 5,
                'stock_sold' => 146,
                'attributes' => ['brand' => 'DataPeak', 'condition' => 'New', 'product_location' => 'Sylhet', 'warranty_status' => '3 year warranty', 'tags' => ['ssd', 'storage', 'usb-c']],
            ],
            [
                'title' => '4K Creator Webcam',
                'category' => 'Cameras',
                'product_type' => 'physical',
                'price' => '139.0000',
                'discount_percentage' => '5.00',
                'discount_label' => null,
                'description' => '4K streaming webcam with HDR, stereo microphones, and auto framing for creators and professionals.',
                'stock_on_hand' => 29,
                'stock_reserved' => 3,
                'stock_sold' => 59,
                'attributes' => ['brand' => 'StreamLift', 'condition' => 'New', 'product_location' => 'Dhaka', 'warranty_status' => '1 year warranty', 'tags' => ['webcam', 'streaming', 'creator']],
            ],
            [
                'title' => 'Travel Smart Watch LTE',
                'category' => 'Watches',
                'product_type' => 'physical',
                'price' => '219.5000',
                'discount_percentage' => '6.00',
                'discount_label' => null,
                'description' => 'LTE-ready smartwatch with fitness tracking, turn-by-turn navigation, and multi-day battery life.',
                'stock_on_hand' => 22,
                'stock_reserved' => 2,
                'stock_sold' => 71,
                'attributes' => ['brand' => 'PulseOrbit', 'condition' => 'New', 'product_location' => 'Khulna', 'warranty_status' => '1 year warranty', 'tags' => ['watch', 'wearable', 'fitness']],
            ],
            [
                'title' => 'Bluetooth Speaker Outdoor Max',
                'category' => 'Audio & Headphones',
                'product_type' => 'physical',
                'price' => '84.0000',
                'discount_percentage' => '15.00',
                'discount_label' => 'Weekend deal',
                'description' => 'Rugged waterproof speaker with stereo pairing, 20-hour playback, and camping-ready build.',
                'stock_on_hand' => 41,
                'stock_reserved' => 3,
                'stock_sold' => 133,
                'attributes' => ['brand' => 'EchoTrail', 'condition' => 'New', 'product_location' => 'Rajshahi', 'warranty_status' => '6 months warranty', 'tags' => ['speaker', 'outdoor', 'portable']],
            ],
            [
                'title' => 'Standing Desk Dual Motor',
                'category' => 'Furniture',
                'product_type' => 'physical',
                'price' => '389.0000',
                'discount_percentage' => '9.00',
                'discount_label' => 'Workspace upgrade',
                'description' => 'Electric height-adjustable desk with dual motors, cable tray, and memory presets for hybrid work setups.',
                'stock_on_hand' => 11,
                'stock_reserved' => 1,
                'stock_sold' => 28,
                'attributes' => ['brand' => 'LiftWorks', 'condition' => 'New', 'product_location' => 'Dhaka', 'warranty_status' => '2 year warranty', 'tags' => ['desk', 'office', 'standing']],
            ],
            [
                'title' => 'Adobe Suite Team License',
                'category' => 'Software Licenses',
                'product_type' => 'digital',
                'price' => '59.0000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Multi-seat digital subscription license delivered after verification with renewal-ready admin transfer.',
                'stock_on_hand' => 500,
                'stock_reserved' => 0,
                'stock_sold' => 214,
                'attributes' => ['brand' => 'CreativeStack', 'digital_product_kind' => 'software_license', 'access_type' => 'team_access', 'license_type' => 'subscription', 'platform' => 'Windows / macOS', 'account_region' => 'Global', 'is_instant_delivery' => false, 'tags' => ['software', 'creative', 'license']],
            ],
            [
                'title' => 'Windows 11 Pro Key',
                'category' => 'Software Licenses',
                'product_type' => 'digital',
                'price' => '24.9000',
                'discount_percentage' => '18.00',
                'discount_label' => 'Volume stock',
                'description' => 'Digital activation key with step-by-step installation guidance and replacement guarantee for unused keys.',
                'stock_on_hand' => 900,
                'stock_reserved' => 0,
                'stock_sold' => 512,
                'attributes' => ['brand' => 'KeyVault', 'digital_product_kind' => 'license_key', 'access_type' => 'license_code', 'license_type' => 'lifetime', 'platform' => 'Windows', 'account_region' => 'Global', 'is_instant_delivery' => true, 'instant_delivery_expiration_hours' => '12', 'tags' => ['windows', 'license', 'instant']],
            ],
            [
                'title' => 'Canva Pro Annual Seat',
                'category' => 'Subscription Packages',
                'product_type' => 'digital',
                'price' => '34.0000',
                'discount_percentage' => '7.00',
                'discount_label' => null,
                'description' => 'Annual design subscription seat delivered to your email with onboarding notes and usage policy.',
                'stock_on_hand' => 320,
                'stock_reserved' => 0,
                'stock_sold' => 168,
                'attributes' => ['brand' => 'DesignPass', 'digital_product_kind' => 'subscription', 'access_type' => 'account_invite', 'subscription_duration' => '12 months', 'platform' => 'Canva', 'account_region' => 'Global', 'is_instant_delivery' => false, 'tags' => ['subscription', 'design', 'productivity']],
            ],
            [
                'title' => 'Steam Wallet Gift Card $50',
                'category' => 'Gift Cards',
                'product_type' => 'digital',
                'price' => '51.0000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Digital gift card code with region-safe redemption notes and buyer support if a code fails validation.',
                'stock_on_hand' => 700,
                'stock_reserved' => 0,
                'stock_sold' => 341,
                'attributes' => ['brand' => 'CardDock', 'digital_product_kind' => 'gift_card', 'access_type' => 'code_delivery', 'platform' => 'Steam', 'account_region' => 'US', 'is_instant_delivery' => true, 'instant_delivery_expiration_hours' => '4', 'tags' => ['gift-card', 'gaming', 'instant']],
            ],
            [
                'title' => 'UI/UX Design Masterclass Bundle',
                'category' => 'Digital Courses',
                'product_type' => 'digital',
                'price' => '79.0000',
                'discount_percentage' => '20.00',
                'discount_label' => 'Course bundle',
                'description' => 'Structured course bundle with recorded lessons, templates, community access, and downloadable resources.',
                'stock_on_hand' => 250,
                'stock_reserved' => 0,
                'stock_sold' => 96,
                'attributes' => ['brand' => 'SkillGrid', 'digital_product_kind' => 'course_bundle', 'access_type' => 'member_portal', 'subscription_duration' => 'lifetime', 'platform' => 'Web portal', 'account_region' => 'Global', 'is_instant_delivery' => false, 'tags' => ['course', 'design', 'learning']],
            ],
            [
                'title' => 'Figma Pro Workspace Seat',
                'category' => 'Software Licenses',
                'product_type' => 'digital',
                'price' => '22.5000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Managed Figma seat assignment with workspace invitation and collaboration handoff within minutes.',
                'stock_on_hand' => 420,
                'stock_reserved' => 0,
                'stock_sold' => 123,
                'attributes' => ['brand' => 'CollabSpace', 'digital_product_kind' => 'workspace_seat', 'access_type' => 'email_invite', 'subscription_duration' => '1 month', 'platform' => 'Figma', 'account_region' => 'Global', 'is_instant_delivery' => true, 'instant_delivery_expiration_hours' => '2', 'tags' => ['figma', 'design', 'seat']],
            ],
            [
                'title' => 'Netflix Family Profile Access',
                'category' => 'Subscription Packages',
                'product_type' => 'digital',
                'price' => '14.0000',
                'discount_percentage' => '5.00',
                'discount_label' => null,
                'description' => 'Shared entertainment profile access with replacement support, delivery notes, and usage window details.',
                'stock_on_hand' => 180,
                'stock_reserved' => 0,
                'stock_sold' => 407,
                'attributes' => ['brand' => 'StreamNest', 'digital_product_kind' => 'subscription_access', 'access_type' => 'credentials', 'subscription_duration' => '30 days', 'platform' => 'Netflix', 'account_region' => 'Global', 'is_instant_delivery' => false, 'tags' => ['streaming', 'subscription', 'entertainment']],
            ],
            [
                'title' => 'Valorant Account Platinum Rank',
                'category' => 'Game Accounts',
                'product_type' => 'digital',
                'price' => '119.0000',
                'discount_percentage' => '10.00',
                'discount_label' => 'Ranked profile',
                'description' => 'High-tier gaming account transfer with secure handoff steps, recovery support, and inventory summary.',
                'stock_on_hand' => 14,
                'stock_reserved' => 1,
                'stock_sold' => 22,
                'attributes' => ['brand' => 'ArenaTrade', 'digital_product_kind' => 'game_account', 'access_type' => 'credentials_transfer', 'platform' => 'PC', 'account_region' => 'APAC', 'is_instant_delivery' => false, 'tags' => ['gaming', 'account', 'valorant']],
            ],
            [
                'title' => 'Seller Storefront Branding Kit',
                'category' => 'Art Supplies',
                'product_type' => 'service',
                'price' => '65.0000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Custom storefront branding package including logo refinement, banner set, color palette, and listing assets.',
                'stock_on_hand' => 24,
                'stock_reserved' => 2,
                'stock_sold' => 31,
                'attributes' => ['brand' => 'StudioNova', 'service_scope' => 'branding', 'turnaround_time' => '3 business days', 'platform' => 'Sellova storefront', 'tags' => ['service', 'branding', 'seller-tools']],
            ],
            [
                'title' => 'Shopify Theme Setup Service',
                'category' => 'Office Supplies',
                'product_type' => 'service',
                'price' => '149.0000',
                'discount_percentage' => '12.00',
                'discount_label' => 'Setup special',
                'description' => 'Theme configuration, navigation setup, app wiring, and launch-ready QA for Shopify storefronts.',
                'stock_on_hand' => 12,
                'stock_reserved' => 1,
                'stock_sold' => 19,
                'attributes' => ['brand' => 'LaunchCrew', 'service_scope' => 'store_setup', 'turnaround_time' => '5 business days', 'platform' => 'Shopify', 'tags' => ['service', 'shopify', 'setup']],
            ],
            [
                'title' => 'Premium CV & LinkedIn Rewrite',
                'category' => 'Office Supplies',
                'product_type' => 'service',
                'price' => '39.0000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Professional resume rewrite, LinkedIn optimization, and recruiter-focused positioning for career transitions.',
                'stock_on_hand' => 35,
                'stock_reserved' => 4,
                'stock_sold' => 84,
                'attributes' => ['brand' => 'CareerCraft', 'service_scope' => 'resume_service', 'turnaround_time' => '48 hours', 'platform' => 'Document delivery', 'tags' => ['service', 'resume', 'career']],
            ],
            [
                'title' => 'Mobile App QA Test Package',
                'category' => 'Office Equipment',
                'product_type' => 'service',
                'price' => '199.0000',
                'discount_percentage' => '15.00',
                'discount_label' => 'Sprint support',
                'description' => 'Structured QA sweep for Android and iOS flows including bug log, reproduction steps, and severity ranking.',
                'stock_on_hand' => 10,
                'stock_reserved' => 1,
                'stock_sold' => 13,
                'attributes' => ['brand' => 'TestForge', 'service_scope' => 'qa_testing', 'turnaround_time' => '4 business days', 'platform' => 'Android / iOS', 'tags' => ['service', 'qa', 'mobile']],
            ],
            [
                'title' => 'eBay Store Policy Writing Service',
                'category' => 'Office Supplies',
                'product_type' => 'service',
                'price' => '54.5000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Buyer-friendly return, shipping, and dispute policy writing tailored for marketplace storefront compliance.',
                'stock_on_hand' => 28,
                'stock_reserved' => 2,
                'stock_sold' => 37,
                'attributes' => ['brand' => 'PolicyWorks', 'service_scope' => 'policy_writing', 'turnaround_time' => '72 hours', 'platform' => 'Marketplace policy docs', 'tags' => ['service', 'policy', 'marketplace']],
            ],
            [
                'title' => 'PlayStation Plus 12-Month Code',
                'category' => 'Gift Cards',
                'product_type' => 'digital',
                'price' => '44.0000',
                'discount_percentage' => '9.00',
                'discount_label' => 'Instant code',
                'description' => 'Instant digital code with redemption notes for PlayStation Plus yearly subscription access.',
                'stock_on_hand' => 640,
                'stock_reserved' => 0,
                'stock_sold' => 289,
                'attributes' => ['brand' => 'GameLoad', 'digital_product_kind' => 'subscription_code', 'access_type' => 'code_delivery', 'platform' => 'PlayStation', 'account_region' => 'US', 'is_instant_delivery' => true, 'instant_delivery_expiration_hours' => '2', 'tags' => ['gaming', 'subscription', 'instant']],
            ],
            [
                'title' => 'Notion Team Knowledge Base Template',
                'category' => 'Digital Courses',
                'product_type' => 'digital',
                'price' => '18.0000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Documented Notion workspace template for operations, onboarding, SOPs, and project tracking.',
                'stock_on_hand' => 850,
                'stock_reserved' => 0,
                'stock_sold' => 198,
                'attributes' => ['brand' => 'OpsCanvas', 'digital_product_kind' => 'template', 'access_type' => 'duplicate_link', 'platform' => 'Notion', 'account_region' => 'Global', 'is_instant_delivery' => true, 'instant_delivery_expiration_hours' => '1', 'tags' => ['notion', 'template', 'ops']],
            ],
            [
                'title' => 'Etsy Listing SEO Audit',
                'category' => 'Office Supplies',
                'product_type' => 'service',
                'price' => '48.0000',
                'discount_percentage' => '0.00',
                'discount_label' => null,
                'description' => 'Targeted Etsy listing audit with title, tag, photography, and category improvement suggestions.',
                'stock_on_hand' => 16,
                'stock_reserved' => 2,
                'stock_sold' => 26,
                'attributes' => ['brand' => 'SearchMint', 'service_scope' => 'seo_audit', 'turnaround_time' => '2 business days', 'platform' => 'Etsy', 'tags' => ['service', 'etsy', 'seo']],
            ],
        ];
    }

    /**
     * @param  list<Category>  $categories
     */
    private static function findCategoryByName(array $categories, string $name): Category
    {
        foreach ($categories as $category) {
            if ((string) $category->name === $name) {
                return $category;
            }
        }

        return $categories[0];
    }

    private static function orderProductTypeSnapshot(Product $product): string
    {
        $attributes = is_array($product->attributes_json) ? $product->attributes_json : [];
        if (($product->product_type ?? '') === 'digital' && filter_var($attributes['is_instant_delivery'] ?? false, FILTER_VALIDATE_BOOL)) {
            return 'instant_delivery';
        }

        return (string) ($product->product_type ?? 'physical');
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

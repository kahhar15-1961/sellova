<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DatabaseSchema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->mysqlTestsAreAvailable()) {
            $this->markTestSkipped('MySQL test database not configured or unreachable. Set TEST_DB_* (or DB_*) in phpunit.xml or the environment.');
        }

        $this->ensureSchemaApplied();
        $this->truncateAllTables();
        $this->ensureEscrowMarketplaceOrderSchema();
        $this->ensureProductsStatusEnumIncludesPublished();
        $this->ensureProductsAttributesJsonColumn();
        $this->ensureOrderCancellationColumns();
        $this->ensureSellerProfileContactColumns();
        $this->ensureUserRolesUpdatedAtColumn();
        $this->ensureEscrowEventsUpdatedAtColumn();
        $this->ensureWalletBalanceSnapshotsUpdatedAtColumn();
        $this->ensureWithdrawalRequestsIdempotencyKeyColumn();
        $this->ensureEscrowTimeoutTables();
        $this->ensurePaymentGatewaysTable();
        $this->ensureNotificationPanelSchema();
        $this->ensureBuyerReviewsTable();
        $this->ensureDigitalEscrowSupportSchema();
    }

    private function ensureBuyerReviewsTable(): void
    {
        if (Schema::hasTable('buyer_reviews')) {
            return;
        }

        Schema::create('buyer_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_user_id');
            $table->unsignedBigInteger('seller_profile_id');
            $table->unsignedBigInteger('buyer_user_id');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 32)->default('visible');
            $table->timestamps();
            $table->unique(['order_id', 'seller_profile_id'], 'uq_buyer_reviews_order_seller');
            $table->index(['buyer_user_id', 'status', 'created_at'], 'idx_buyer_reviews_buyer_status');
        });
    }

    private function ensurePaymentGatewaysTable(): void
    {
        if (Schema::hasTable('payment_gateways')) {
            return;
        }

        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 160);
            $table->string('method', 32);
            $table->string('driver', 32)->default('manual');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->json('supported_methods')->nullable();
            $table->string('checkout_url', 512)->nullable();
            $table->string('callback_url', 512)->nullable();
            $table->string('webhook_url', 512)->nullable();
            $table->string('public_key', 256)->nullable();
            $table->string('merchant_id', 256)->nullable();
            $table->longText('credentials_json')->nullable();
            $table->json('extra_json')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    private function ensureNotificationPanelSchema(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('notifications')) {
            return;
        }

        foreach ([
            'type' => 'VARCHAR(128) NULL AFTER channel',
            'title' => 'VARCHAR(191) NULL AFTER template_code',
            'message' => 'TEXT NULL AFTER title',
            'icon' => 'VARCHAR(64) NULL AFTER message',
            'color' => 'VARCHAR(32) NULL AFTER icon',
            'action_url' => 'VARCHAR(2048) NULL AFTER color',
            'metadata_json' => 'JSON NULL AFTER payload_json',
            'user_role' => 'VARCHAR(32) NULL AFTER user_id',
            'priority' => 'VARCHAR(16) NULL AFTER user_role',
        ] as $column => $definition) {
            if ($schema->hasColumn('notifications', $column)) {
                continue;
            }

            try {
                DB::connection()->statement("ALTER TABLE notifications ADD COLUMN {$column} {$definition}");
            } catch (\Throwable) {
                // Column may already exist.
            }
        }
    }

    private function mysqlTestsAreAvailable(): bool
    {
        try {
            if (config('database.default') !== 'mysql') {
                return false;
            }
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureSchemaApplied(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if ($schema->hasTable('wallets')) {
            return;
        }

        DatabaseSchema::applyCanonicalSchema();
    }

    private function truncateAllTables(): void
    {
        $tables = [
            'cache', 'cache_locks', 'sessions',
            'outbox_events', 'audit_logs', 'notifications', 'escrow_timeout_events', 'buyer_reviews', 'reviews', 'dispute_decisions', 'dispute_evidences',
            'dispute_cases', 'membership_subscriptions', 'payout_accounts', 'withdrawal_transactions',
            'withdrawal_requests', 'wallet_top_up_requests', 'wallet_balance_snapshots', 'wallet_ledger_entries', 'wallet_ledger_batches',
            'wallet_holds', 'wallets', 'escrow_events', 'escrow_accounts', 'payment_webhook_events',
            'payment_transactions', 'payment_intents', 'payment_gateways', 'idempotency_keys', 'order_state_transitions',
            'order_message_attachments', 'digital_delivery_files', 'digital_deliveries',
            'order_items', 'orders', 'commission_rules', 'membership_plans', 'cart_items', 'carts',
            'inventory_records', 'product_variants', 'products', 'seller_shipping_methods', 'shipping_methods',
            'seller_category_requests', 'categories', 'storefronts', 'kyc_documents',
            'kyc_verifications', 'seller_profiles', 'role_permissions', 'user_roles', 'permissions', 'roles',
            'user_auth_tokens', 'users',
        ];

        $conn = DB::connection();
        $conn->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if ($conn->getSchemaBuilder()->hasTable($table)) {
                $conn->table($table)->truncate();
            }
        }
        $conn->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Keeps integration tests working against databases bootstrapped from an older CANONICAL_SCHEMA snapshot.
     */
    private function ensureEscrowMarketplaceOrderSchema(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('orders')) {
            return;
        }

        try {
            DB::connection()->statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'draft','pending_payment','paid','paid_in_escrow','escrow_funded',
                'processing','delivery_submitted','buyer_review','shipped_or_delivered','completed','cancelled','refunded','disputed'
            ) NOT NULL");
        } catch (\Throwable) {
            // Definition may already match; ignore.
        }

        foreach ([
            'seller_user_id' => 'BIGINT UNSIGNED NULL AFTER buyer_user_id',
            'primary_product_id' => 'BIGINT UNSIGNED NULL AFTER seller_user_id',
            'product_type' => 'VARCHAR(32) NULL AFTER primary_product_id',
            'fulfillment_state' => "VARCHAR(64) NOT NULL DEFAULT 'not_started' AFTER status",
            'seller_deadline_at' => 'DATETIME(6) NULL AFTER delivered_at',
            'seller_reminder_at' => 'DATETIME(6) NULL AFTER seller_deadline_at',
            'delivery_submitted_at' => 'DATETIME(6) NULL AFTER delivered_at',
            'buyer_review_started_at' => 'DATETIME(6) NULL AFTER delivery_submitted_at',
            'buyer_review_expires_at' => 'DATETIME(6) NULL AFTER buyer_review_started_at',
            'reminder_1_at' => 'DATETIME(6) NULL AFTER buyer_review_expires_at',
            'reminder_2_at' => 'DATETIME(6) NULL AFTER reminder_1_at',
            'escalation_at' => 'DATETIME(6) NULL AFTER reminder_2_at',
            'escalation_warning_at' => 'DATETIME(6) NULL AFTER escalation_at',
            'auto_release_at' => 'DATETIME(6) NULL AFTER escalation_at',
            'release_eligible_at' => 'DATETIME(6) NULL AFTER buyer_review_started_at',
            'expires_at' => 'DATETIME(6) NULL AFTER release_eligible_at',
            'unpaid_reminder_at' => 'DATETIME(6) NULL AFTER expires_at',
            'timeout_policy_snapshot_json' => 'JSON NULL AFTER auto_release_at',
        ] as $column => $definition) {
            if ($schema->hasColumn('orders', $column)) {
                continue;
            }
            try {
                DB::connection()->statement("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
            } catch (\Throwable) {
                // Column may already exist.
            }
        }

        try {
            DB::connection()->statement("ALTER TABLE order_items MODIFY COLUMN product_type_snapshot ENUM(
                'physical','digital','instant_delivery','service','manual_delivery'
            ) NOT NULL");
        } catch (\Throwable) {
            // Definition may already match; ignore.
        }

        if ($schema->hasTable('dispute_evidences')) {
            try {
                DB::connection()->statement("ALTER TABLE dispute_evidences MODIFY COLUMN evidence_type ENUM(
                    'text','image','video','document','tracking','chat_message','delivery_proof','screenshot','file'
                ) NOT NULL");
            } catch (\Throwable) {
                // Definition may already match; ignore.
            }
        }
    }

    private function ensureProductsStatusEnumIncludesPublished(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('products')) {
            return;
        }

        try {
            DB::connection()->statement("ALTER TABLE products MODIFY COLUMN status ENUM(
                'draft','active','inactive','archived','published'
            ) NOT NULL DEFAULT 'draft'");
        } catch (\Throwable) {
            // Definition may already match; ignore.
        }
    }

    private function ensureProductsAttributesJsonColumn(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('products') || $schema->hasColumn('products', 'attributes_json')) {
            return;
        }

        try {
            DB::connection()->statement('ALTER TABLE products ADD COLUMN attributes_json JSON NULL AFTER images_json');
        } catch (\Throwable) {
            // Column may already exist.
        }
    }

    private function ensureOrderCancellationColumns(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('orders')) {
            return;
        }

        try {
            if (! $schema->hasColumn('orders', 'cancelled_at')) {
                DB::connection()->statement('ALTER TABLE orders ADD COLUMN cancelled_at DATETIME(6) NULL AFTER completed_at');
            }
            if (! $schema->hasColumn('orders', 'cancel_reason')) {
                DB::connection()->statement('ALTER TABLE orders ADD COLUMN cancel_reason VARCHAR(500) NULL AFTER cancelled_at');
            }
        } catch (\Throwable) {
            // Columns may already exist or the test database may apply the latest migrations directly.
        }
    }

    private function ensureSellerProfileContactColumns(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('seller_profiles')) {
            return;
        }

        foreach ([
            'contact_email' => 'VARCHAR(191) NULL AFTER banner_image_url',
            'contact_phone' => 'VARCHAR(40) NULL AFTER contact_email',
            'address_line' => 'VARCHAR(255) NULL AFTER contact_phone',
            'city' => 'VARCHAR(120) NULL AFTER address_line',
            'region' => 'VARCHAR(120) NULL AFTER city',
            'postal_code' => 'VARCHAR(40) NULL AFTER region',
            'country' => 'VARCHAR(120) NULL AFTER postal_code',
        ] as $column => $definition) {
            if ($schema->hasColumn('seller_profiles', $column)) {
                continue;
            }
            try {
                DB::connection()->statement("ALTER TABLE seller_profiles ADD COLUMN {$column} {$definition}");
            } catch (\Throwable) {
                // Column may already exist; ignore.
            }
        }
    }

    private function ensureUserRolesUpdatedAtColumn(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('user_roles') || $schema->hasColumn('user_roles', 'updated_at')) {
            return;
        }

        try {
            DB::connection()->statement(
                'ALTER TABLE user_roles ADD COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER created_at'
            );
        } catch (\Throwable) {
            // Column may already exist; ignore.
        }
    }

    private function ensureEscrowEventsUpdatedAtColumn(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('escrow_events') || $schema->hasColumn('escrow_events', 'updated_at')) {
            return;
        }

        try {
            DB::connection()->statement(
                'ALTER TABLE escrow_events ADD COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER created_at'
            );
        } catch (\Throwable) {
            // Column may already exist; ignore.
        }
    }

    private function ensureWalletBalanceSnapshotsUpdatedAtColumn(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('wallet_balance_snapshots') || $schema->hasColumn('wallet_balance_snapshots', 'updated_at')) {
            return;
        }

        try {
            DB::connection()->statement(
                'ALTER TABLE wallet_balance_snapshots ADD COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER created_at'
            );
        } catch (\Throwable) {
            // Column may already exist; ignore.
        }
    }

    private function ensureWithdrawalRequestsIdempotencyKeyColumn(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('withdrawal_requests') || $schema->hasColumn('withdrawal_requests', 'idempotency_key')) {
            return;
        }

        $conn = DB::connection();
        $conn->statement('ALTER TABLE withdrawal_requests ADD COLUMN idempotency_key VARCHAR(191) NULL AFTER uuid');
        $conn->statement('UPDATE withdrawal_requests SET idempotency_key = CONCAT(\'legacy-withdrawal-\', id) WHERE idempotency_key IS NULL');
        $conn->statement('ALTER TABLE withdrawal_requests MODIFY COLUMN idempotency_key VARCHAR(191) NOT NULL');
        try {
            $conn->statement('ALTER TABLE withdrawal_requests ADD UNIQUE KEY uq_withdrawal_requests_idempotency_key (idempotency_key)');
        } catch (\Throwable) {
            // Index may already exist.
        }
    }

    private function ensureEscrowTimeoutTables(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('escrow_timeout_settings')) {
            DB::connection()->statement("CREATE TABLE escrow_timeout_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                unpaid_order_expiration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
                unpaid_order_warning_minutes INT UNSIGNED NOT NULL DEFAULT 10,
                seller_fulfillment_deadline_hours INT UNSIGNED NOT NULL DEFAULT 24,
                seller_fulfillment_warning_hours INT UNSIGNED NOT NULL DEFAULT 2,
                buyer_review_deadline_hours INT UNSIGNED NOT NULL DEFAULT 72,
                buyer_review_reminder_1_hours INT UNSIGNED NOT NULL DEFAULT 24,
                buyer_review_reminder_2_hours INT UNSIGNED NOT NULL DEFAULT 48,
                escalation_warning_minutes INT UNSIGNED NOT NULL DEFAULT 60,
                seller_min_fulfillment_hours INT UNSIGNED NOT NULL DEFAULT 1,
                seller_max_fulfillment_hours INT UNSIGNED NOT NULL DEFAULT 168,
                buyer_min_review_hours INT UNSIGNED NOT NULL DEFAULT 1,
                buyer_max_review_hours INT UNSIGNED NOT NULL DEFAULT 168,
                auto_escalation_after_review_expiry TINYINT(1) NOT NULL DEFAULT 1,
                auto_cancel_unpaid_orders TINYINT(1) NOT NULL DEFAULT 1,
                auto_release_after_buyer_timeout TINYINT(1) NOT NULL DEFAULT 0,
                auto_create_dispute_on_timeout TINYINT(1) NOT NULL DEFAULT 0,
                dispute_review_queue_enabled TINYINT(1) NOT NULL DEFAULT 1,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )");
        }
        foreach ([
            'unpaid_order_warning_minutes' => 'INT UNSIGNED NOT NULL DEFAULT 10 AFTER unpaid_order_expiration_minutes',
            'seller_fulfillment_warning_hours' => 'INT UNSIGNED NOT NULL DEFAULT 2 AFTER seller_fulfillment_deadline_hours',
            'escalation_warning_minutes' => 'INT UNSIGNED NOT NULL DEFAULT 60 AFTER buyer_review_reminder_2_hours',
        ] as $column => $definition) {
            if ($schema->hasColumn('escrow_timeout_settings', $column)) {
                continue;
            }
            try {
                DB::connection()->statement("ALTER TABLE escrow_timeout_settings ADD COLUMN {$column} {$definition}");
            } catch (\Throwable) {
                // Column may already exist.
            }
        }
        if (! $schema->hasTable('escrow_timeout_events')) {
            DB::connection()->statement("CREATE TABLE escrow_timeout_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                uuid VARCHAR(191) NOT NULL UNIQUE,
                order_id BIGINT UNSIGNED NOT NULL,
                escrow_account_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(96) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'processed',
                action_taken VARCHAR(96) NULL,
                metadata_json JSON NULL,
                scheduled_for TIMESTAMP NULL,
                processed_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY uq_timeout_events_order_type (order_id, event_type)
            )");
        }
    }

    protected function ensureDigitalEscrowSupportSchema(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('orders')) {
            return;
        }

        foreach ([
            'escrow_status' => 'VARCHAR(32) NULL AFTER status',
            'escrow_amount' => 'DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER fee_amount',
            'escrow_fee' => 'DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER escrow_amount',
            'escrow_started_at' => 'DATETIME(6) NULL AFTER placed_at',
            'escrow_expires_at' => 'DATETIME(6) NULL AFTER escrow_started_at',
            'escrow_released_at' => 'DATETIME(6) NULL AFTER escrow_expires_at',
            'escrow_auto_release_at' => 'DATETIME(6) NULL AFTER escrow_released_at',
            'escrow_release_method' => 'VARCHAR(32) NULL AFTER escrow_auto_release_at',
            'dispute_deadline_at' => 'DATETIME(6) NULL AFTER escrow_release_method',
            'delivery_deadline_at' => 'DATETIME(6) NULL AFTER dispute_deadline_at',
            'delivery_status' => 'VARCHAR(32) NULL AFTER delivery_deadline_at',
            'delivery_note' => 'TEXT NULL AFTER delivery_status',
            'delivery_version' => 'VARCHAR(32) NULL AFTER delivery_note',
            'delivery_files_count' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER delivery_version',
            'buyer_confirmed_at' => 'DATETIME(6) NULL AFTER delivery_files_count',
        ] as $column => $definition) {
            if ($schema->hasColumn('orders', $column)) {
                continue;
            }
            try {
                DB::connection()->statement("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($schema->hasTable('escrow_accounts')) {
            foreach ([
                'escrow_fee' => 'DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER held_amount',
                'started_at' => 'DATETIME(6) NULL AFTER held_at',
                'expires_at' => 'DATETIME(6) NULL AFTER started_at',
                'released_at' => 'DATETIME(6) NULL AFTER expires_at',
                'auto_release_at' => 'DATETIME(6) NULL AFTER released_at',
                'release_method' => 'VARCHAR(32) NULL AFTER auto_release_at',
                'dispute_deadline_at' => 'DATETIME(6) NULL AFTER release_method',
                'delivery_deadline_at' => 'DATETIME(6) NULL AFTER dispute_deadline_at',
            ] as $column => $definition) {
                if ($schema->hasColumn('escrow_accounts', $column)) {
                    continue;
                }
                try {
                    DB::connection()->statement("ALTER TABLE escrow_accounts ADD COLUMN {$column} {$definition}");
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        if (! $schema->hasTable('digital_deliveries')) {
            Schema::create('digital_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('seller_user_id');
                $table->unsignedBigInteger('buyer_user_id');
                $table->string('status', 32)->default('pending');
                $table->string('version', 32)->nullable();
                $table->string('external_url', 1000)->nullable();
                $table->text('delivery_note')->nullable();
                $table->unsignedInteger('files_count')->default(0);
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('buyer_confirmed_at')->nullable();
                $table->timestamp('revision_requested_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('digital_delivery_files')) {
            Schema::create('digital_delivery_files', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('digital_delivery_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('uploaded_by_user_id');
                $table->string('disk', 32)->default('local');
                $table->string('path', 1000);
                $table->string('original_name', 255);
                $table->string('mime_type', 191)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('visibility', 32)->default('escrow');
                $table->string('scan_status', 32)->default('pending');
                $table->timestamp('scan_completed_at')->nullable();
                $table->timestamp('downloaded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('order_message_attachments')) {
            Schema::create('order_message_attachments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('chat_message_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('uploaded_by_user_id');
                $table->string('disk', 32)->default('local');
                $table->string('path', 1000);
                $table->string('original_name', 255);
                $table->string('mime_type', 191)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('attachment_kind', 32)->default('file');
                $table->string('visibility', 32)->default('escrow');
                $table->string('scan_status', 32)->default('pending');
                $table->timestamp('scan_completed_at')->nullable();
                $table->timestamps();
            });
        }
    }
}

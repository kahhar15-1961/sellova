<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
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
        $this->ensureOrdersStatusEnumIncludesPaidInEscrow();
        $this->ensureProductsStatusEnumIncludesPublished();
        $this->ensureUserRolesUpdatedAtColumn();
        $this->ensureEscrowEventsUpdatedAtColumn();
        $this->ensureWalletBalanceSnapshotsUpdatedAtColumn();
        $this->ensureWithdrawalRequestsIdempotencyKeyColumn();
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
    private function ensureOrdersStatusEnumIncludesPaidInEscrow(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (! $schema->hasTable('orders')) {
            return;
        }

        try {
            DB::connection()->statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'draft','pending_payment','paid','paid_in_escrow',
                'processing','shipped_or_delivered','completed','cancelled','refunded','disputed'
            ) NOT NULL");
        } catch (\Throwable) {
            // Definition may already match; ignore.
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
}

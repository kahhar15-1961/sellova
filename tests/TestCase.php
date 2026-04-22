<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\DatabaseSchema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('TEST_DB_AVAILABLE') || TEST_DB_AVAILABLE !== true) {
            $this->markTestSkipped('MySQL test database not configured. Set TEST_DB_* env vars.');
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

    private function ensureSchemaApplied(): void
    {
        // If the canonical tables already exist, keep them.
        $schema = Capsule::connection()->getSchemaBuilder();
        if ($schema->hasTable('wallets')) {
            return;
        }

        DatabaseSchema::applyCanonicalSchema();
    }

    private function truncateAllTables(): void
    {
        $tables = [
            'outbox_events','audit_logs','notifications','reviews','dispute_decisions','dispute_evidences',
            'dispute_cases','membership_subscriptions','payout_accounts','withdrawal_transactions',
            'withdrawal_requests','wallet_balance_snapshots','wallet_ledger_entries','wallet_ledger_batches',
            'wallet_holds','wallets','escrow_events','escrow_accounts','payment_webhook_events',
            'payment_transactions','payment_intents','idempotency_keys','order_state_transitions',
            'order_items','orders','commission_rules','membership_plans','cart_items','carts',
            'inventory_records','product_variants','products','categories','storefronts','kyc_documents',
            'kyc_verifications','seller_profiles','role_permissions','user_roles','permissions','roles',
            'user_auth_tokens','users',
        ];

        Capsule::connection()->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if (Capsule::connection()->getSchemaBuilder()->hasTable($table)) {
                Capsule::connection()->table($table)->truncate();
            }
        }
        Capsule::connection()->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Keeps integration tests working against databases bootstrapped from an older CANONICAL_SCHEMA snapshot.
     */
    private function ensureOrdersStatusEnumIncludesPaidInEscrow(): void
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        if (! $schema->hasTable('orders')) {
            return;
        }

        try {
            Capsule::connection()->statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'draft','pending_payment','paid','paid_in_escrow',
                'processing','shipped_or_delivered','completed','cancelled','refunded','disputed'
            ) NOT NULL");
        } catch (\Throwable) {
            // Definition may already match; ignore.
        }
    }

    private function ensureProductsStatusEnumIncludesPublished(): void
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        if (! $schema->hasTable('products')) {
            return;
        }

        try {
            Capsule::connection()->statement("ALTER TABLE products MODIFY COLUMN status ENUM(
                'draft','active','inactive','archived','published'
            ) NOT NULL DEFAULT 'draft'");
        } catch (\Throwable) {
            // Definition may already match; ignore.
        }
    }

    private function ensureUserRolesUpdatedAtColumn(): void
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        if (! $schema->hasTable('user_roles') || $schema->hasColumn('user_roles', 'updated_at')) {
            return;
        }

        try {
            Capsule::connection()->statement(
                'ALTER TABLE user_roles ADD COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER created_at'
            );
        } catch (\Throwable) {
            // Column may already exist; ignore.
        }
    }

    private function ensureEscrowEventsUpdatedAtColumn(): void
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        if (! $schema->hasTable('escrow_events') || $schema->hasColumn('escrow_events', 'updated_at')) {
            return;
        }

        try {
            Capsule::connection()->statement(
                'ALTER TABLE escrow_events ADD COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER created_at'
            );
        } catch (\Throwable) {
            // Column may already exist; ignore.
        }
    }

    private function ensureWalletBalanceSnapshotsUpdatedAtColumn(): void
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        if (! $schema->hasTable('wallet_balance_snapshots') || $schema->hasColumn('wallet_balance_snapshots', 'updated_at')) {
            return;
        }

        try {
            Capsule::connection()->statement(
                'ALTER TABLE wallet_balance_snapshots ADD COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER created_at'
            );
        } catch (\Throwable) {
            // Column may already exist; ignore.
        }
    }

    private function ensureWithdrawalRequestsIdempotencyKeyColumn(): void
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        if (! $schema->hasTable('withdrawal_requests') || $schema->hasColumn('withdrawal_requests', 'idempotency_key')) {
            return;
        }

        $conn = Capsule::connection();
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


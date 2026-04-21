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
            'kyc_verifications','seller_profiles','role_permissions','user_roles','permissions','roles','users',
        ];

        Capsule::connection()->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if (Capsule::connection()->getSchemaBuilder()->hasTable($table)) {
                Capsule::connection()->table($table)->truncate();
            }
        }
        Capsule::connection()->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration {
    public function up(): void
    {
        $sqlPath = base_path('CANONICAL_SCHEMA.sql');
        if (! File::exists($sqlPath)) {
            throw new RuntimeException('CANONICAL_SCHEMA.sql not found at project root.');
        }

        $sql = File::get($sqlPath);
        $statements = $this->splitSqlStatements($sql);

        foreach ($statements as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'outbox_events','audit_logs','notifications','reviews','dispute_decisions','dispute_evidences',
            'dispute_cases','membership_subscriptions','payout_accounts','withdrawal_transactions',
            'withdrawal_requests','wallet_balance_snapshots','wallet_ledger_entries','wallet_ledger_batches',
            'wallet_holds','wallets','escrow_events','escrow_accounts','payment_webhook_events',
            'payment_transactions','payment_intents','idempotency_keys','order_state_transitions',
            'order_items','orders','commission_rules','membership_plans','cart_items','carts',
            'inventory_records','product_variants','products','categories','storefronts','kyc_documents',
            'kyc_verifications','seller_profiles','role_permissions','user_roles','permissions','roles','users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Splits SQL script into executable statements.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';

            if ($ch === "'" && $prev !== '\\' && ! $inDouble) {
                $inSingle = ! $inSingle;
            } elseif ($ch === '"' && $prev !== '\\' && ! $inSingle) {
                $inDouble = ! $inDouble;
            }

            $buffer .= $ch;
            if ($ch === ';' && ! $inSingle && ! $inDouble) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return array_values(array_filter($statements, static function (string $stmt): bool {
            $clean = ltrim($stmt);
            return $clean !== '' && ! str_starts_with($clean, '--');
        }));
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('withdrawal_requests', 'idempotency_key')) {
            return;
        }

        DB::statement('ALTER TABLE withdrawal_requests ADD COLUMN idempotency_key VARCHAR(191) NULL AFTER uuid');
        DB::statement('UPDATE withdrawal_requests SET idempotency_key = CONCAT(\'legacy-withdrawal-\', id) WHERE idempotency_key IS NULL');
        DB::statement('ALTER TABLE withdrawal_requests MODIFY COLUMN idempotency_key VARCHAR(191) NOT NULL');
        DB::statement('ALTER TABLE withdrawal_requests ADD UNIQUE KEY uq_withdrawal_requests_idempotency_key (idempotency_key)');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('withdrawal_requests', 'idempotency_key')) {
            return;
        }

        DB::statement('ALTER TABLE withdrawal_requests DROP INDEX uq_withdrawal_requests_idempotency_key');
        DB::statement('ALTER TABLE withdrawal_requests DROP COLUMN idempotency_key');
    }
};

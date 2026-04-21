<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extends `orders.status` with `paid_in_escrow` for single-seller checkout orchestration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'draft','pending_payment','paid','paid_in_escrow',
            'processing','shipped_or_delivered','completed','cancelled','refunded','disputed'
        ) NOT NULL");
    }

    public function down(): void
    {
        // Irreversible if any row uses `paid_in_escrow`; leave empty in production rollbacks.
    }
};

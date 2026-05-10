<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE products MODIFY status ENUM('draft','active','inactive','archived','published','pending_review','rejected','out_of_stock') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("UPDATE products SET status = 'draft' WHERE status IN ('pending_review','rejected')");
        DB::statement("UPDATE products SET status = 'inactive' WHERE status = 'out_of_stock'");
        DB::statement("ALTER TABLE products MODIFY status ENUM('draft','active','inactive','archived','published') NOT NULL DEFAULT 'draft'");
    }
};

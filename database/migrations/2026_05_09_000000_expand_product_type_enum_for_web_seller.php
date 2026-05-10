<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE products MODIFY product_type ENUM('physical','digital','instant_delivery','service','manual_delivery') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE products SET product_type = 'digital' WHERE product_type = 'instant_delivery'");
        DB::statement("UPDATE products SET product_type = 'manual_delivery' WHERE product_type = 'service'");
        DB::statement("ALTER TABLE products MODIFY product_type ENUM('physical','digital','manual_delivery') NOT NULL");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM('draft','pending_payment','paid','paid_in_escrow','escrow_funded','processing','delivery_submitted','buyer_review','shipped_or_delivered','completed','cancelled','refunded','disputed') NOT NULL");
        DB::statement("ALTER TABLE order_items MODIFY product_type_snapshot ENUM('physical','digital','instant_delivery','service','manual_delivery') NOT NULL");
        DB::statement("ALTER TABLE dispute_evidences MODIFY evidence_type ENUM('text','image','video','document','tracking','chat_message','delivery_proof','screenshot','file') NOT NULL");
    }
};


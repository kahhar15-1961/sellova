<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->expandOrderStatusEnum();
        $this->expandProductTypeEnum();
        $this->expandDisputeEvidenceEnum();

        Schema::table('orders', static function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'seller_user_id')) {
                $table->unsignedBigInteger('seller_user_id')->nullable()->after('buyer_user_id');
                $table->index(['seller_user_id', 'status', 'id'], 'idx_orders_seller_status_id');
            }
            if (! Schema::hasColumn('orders', 'primary_product_id')) {
                $table->unsignedBigInteger('primary_product_id')->nullable()->after('seller_user_id');
                $table->index(['primary_product_id'], 'idx_orders_primary_product');
            }
            if (! Schema::hasColumn('orders', 'product_type')) {
                $table->string('product_type', 32)->nullable()->after('primary_product_id');
                $table->index(['product_type', 'status'], 'idx_orders_product_type_status');
            }
            if (! Schema::hasColumn('orders', 'fulfillment_state')) {
                $table->string('fulfillment_state', 64)->default('not_started')->after('status');
            }
            if (! Schema::hasColumn('orders', 'delivery_submitted_at')) {
                $table->timestamp('delivery_submitted_at')->nullable()->after('delivered_at');
            }
            if (! Schema::hasColumn('orders', 'buyer_review_started_at')) {
                $table->timestamp('buyer_review_started_at')->nullable()->after('delivery_submitted_at');
            }
            if (! Schema::hasColumn('orders', 'release_eligible_at')) {
                $table->timestamp('release_eligible_at')->nullable()->after('buyer_review_started_at');
            }
            if (! Schema::hasColumn('orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('release_eligible_at');
                $table->index(['status', 'expires_at'], 'idx_orders_status_expires_at');
            }
        });

        DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->join('seller_profiles as sp', 'sp.id', '=', 'oi.seller_profile_id')
            ->whereNull('o.seller_user_id')
            ->update([
                'o.seller_user_id' => DB::raw('sp.user_id'),
                'o.primary_product_id' => DB::raw('oi.product_id'),
                'o.product_type' => DB::raw("CASE WHEN oi.product_type_snapshot = 'manual_delivery' THEN 'service' ELSE oi.product_type_snapshot END"),
            ]);

        Schema::table('chat_threads', static function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_threads', 'purpose')) {
                $table->string('purpose', 32)->default('conversation')->after('kind');
                $table->index(['order_id', 'purpose'], 'idx_chat_threads_order_purpose');
            }
        });

        Schema::table('chat_messages', static function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_messages', 'marker_type')) {
                $table->string('marker_type', 64)->nullable()->after('body');
            }
            if (! Schema::hasColumn('chat_messages', 'artifact_type')) {
                $table->string('artifact_type', 64)->nullable()->after('marker_type');
            }
            if (! Schema::hasColumn('chat_messages', 'is_delivery_proof')) {
                $table->boolean('is_delivery_proof')->default(false)->after('artifact_type');
                $table->index(['thread_id', 'is_delivery_proof'], 'idx_chat_messages_thread_proof');
            }
        });

        Schema::table('dispute_evidences', static function (Blueprint $table): void {
            if (! Schema::hasColumn('dispute_evidences', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('dispute_case_id');
                $table->index(['order_id', 'dispute_case_id'], 'idx_dispute_evidences_order_case');
            }
            if (! Schema::hasColumn('dispute_evidences', 'message_id')) {
                $table->unsignedBigInteger('message_id')->nullable()->after('submitted_by_user_id');
            }
            if (! Schema::hasColumn('dispute_evidences', 'file_id')) {
                $table->string('file_id', 191)->nullable()->after('message_id');
            }
            if (! Schema::hasColumn('dispute_evidences', 'note')) {
                $table->text('note')->nullable()->after('file_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispute_evidences', static function (Blueprint $table): void {
            foreach (['note', 'file_id', 'message_id', 'order_id'] as $column) {
                if (Schema::hasColumn('dispute_evidences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('chat_messages', static function (Blueprint $table): void {
            foreach (['is_delivery_proof', 'artifact_type', 'marker_type'] as $column) {
                if (Schema::hasColumn('chat_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('chat_threads', static function (Blueprint $table): void {
            if (Schema::hasColumn('chat_threads', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });

        Schema::table('orders', static function (Blueprint $table): void {
            foreach (['expires_at', 'release_eligible_at', 'buyer_review_started_at', 'delivery_submitted_at', 'fulfillment_state', 'product_type', 'primary_product_id', 'seller_user_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function expandOrderStatusEnum(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM('draft','pending_payment','paid','paid_in_escrow','escrow_funded','processing','delivery_submitted','buyer_review','shipped_or_delivered','completed','cancelled','refunded','disputed') NOT NULL");
    }

    private function expandProductTypeEnum(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE order_items MODIFY product_type_snapshot ENUM('physical','digital','instant_delivery','service','manual_delivery') NOT NULL");
    }

    private function expandDisputeEvidenceEnum(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE dispute_evidences MODIFY evidence_type ENUM('text','image','video','document','tracking','chat_message','delivery_proof','screenshot','file') NOT NULL");
    }
};

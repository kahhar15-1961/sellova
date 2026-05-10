<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seller_warehouses')) {
            Schema::create('seller_warehouses', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('seller_profile_id');
                $table->string('name', 191);
                $table->string('code', 32)->nullable();
                $table->text('address')->nullable();
                $table->string('city', 120)->nullable();
                $table->string('contact_person', 191)->nullable();
                $table->string('phone', 80)->nullable();
                $table->string('status', 32)->default('active');
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['seller_profile_id', 'status'], 'idx_seller_warehouses_seller_status');
                $table->foreign('seller_profile_id', 'fk_seller_warehouses_seller')
                    ->references('id')->on('seller_profiles')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('seller_profile_id');
                $table->unsignedBigInteger('seller_warehouse_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->string('movement_type', 32);
                $table->integer('quantity_delta');
                $table->unsignedBigInteger('stock_after')->default(0);
                $table->string('reason', 191)->nullable();
                $table->string('reference', 191)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['seller_profile_id', 'created_at'], 'idx_stock_movements_seller_created');
                $table->index(['product_id', 'product_variant_id'], 'idx_stock_movements_product_variant');
                $table->foreign('seller_profile_id', 'fk_stock_movements_seller')
                    ->references('id')->on('seller_profiles')
                    ->cascadeOnDelete();
                $table->foreign('seller_warehouse_id', 'fk_stock_movements_warehouse')
                    ->references('id')->on('seller_warehouses')
                    ->nullOnDelete();
                $table->foreign('product_id', 'fk_stock_movements_product')
                    ->references('id')->on('products')
                    ->nullOnDelete();
                $table->foreign('product_variant_id', 'fk_stock_movements_variant')
                    ->references('id')->on('product_variants')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('seller_warehouses');
    }
};

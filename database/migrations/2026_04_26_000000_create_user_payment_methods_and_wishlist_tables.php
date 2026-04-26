<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_payment_methods')) {
            Schema::create('user_payment_methods', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->unsignedBigInteger('user_id');
                $table->string('kind', 50)->default('card');
                $table->string('label', 191);
                $table->string('subtitle', 191)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index('user_id', 'idx_user_payment_methods_user_id');
                $table->index(['user_id', 'is_default'], 'idx_user_payment_methods_user_default');
                $table->unique('uuid', 'uq_user_payment_methods_uuid');
            });
        }

        if (! Schema::hasTable('user_wishlist_items')) {
            Schema::create('user_wishlist_items', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('product_id');
                $table->timestamps();

                $table->index('user_id', 'idx_user_wishlist_items_user_id');
                $table->index('product_id', 'idx_user_wishlist_items_product_id');
                $table->unique(['user_id', 'product_id'], 'uq_user_wishlist_items_user_product');
                $table->unique('uuid', 'uq_user_wishlist_items_uuid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_wishlist_items')) {
            Schema::drop('user_wishlist_items');
        }
        if (Schema::hasTable('user_payment_methods')) {
            Schema::drop('user_payment_methods');
        }
    }
};


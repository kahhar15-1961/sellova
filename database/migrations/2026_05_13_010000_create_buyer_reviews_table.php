<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('buyer_reviews')) {
            return;
        }

        Schema::create('buyer_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('seller_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->restrictOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->enum('status', ['visible', 'hidden', 'flagged'])->default('visible');
            $table->timestamps(6);

            $table->unique(['order_id', 'seller_profile_id'], 'uq_buyer_reviews_order_seller');
            $table->index(['buyer_user_id', 'status', 'created_at'], 'idx_buyer_reviews_buyer_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_reviews');
    }
};

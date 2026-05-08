<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable()->index();
            $table->string('code', 64)->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('badge', 48)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('discount_type', 32);
            $table->decimal('discount_value', 12, 4);
            $table->decimal('min_spend', 12, 4)->default(0);
            $table->decimal('max_discount_amount', 12, 4)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('promotions')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'WELCOME10',
                'title' => 'Welcome Bonus',
                'description' => '10% off on your first or next eligible order.',
                'badge' => '10% OFF',
                'currency' => 'USD',
                'discount_type' => 'percentage',
                'discount_value' => '0.1000',
                'min_spend' => '0.0000',
                'max_discount_amount' => '25.0000',
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'SAVE50',
                'title' => 'Instant Savings',
                'description' => 'Flat $50 discount on larger carts.',
                'badge' => '$50 OFF',
                'currency' => 'USD',
                'discount_type' => 'fixed',
                'discount_value' => '50.0000',
                'min_spend' => '500.0000',
                'max_discount_amount' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'FREESHIP',
                'title' => 'Free Shipping',
                'description' => 'Shipping waiver for eligible orders.',
                'badge' => 'FREE SHIP',
                'currency' => 'USD',
                'discount_type' => 'shipping',
                'discount_value' => '1.0000',
                'min_spend' => '0.0000',
                'max_discount_amount' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};

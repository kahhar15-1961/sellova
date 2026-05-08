<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 96)->unique();
            $table->string('name', 191);
            $table->decimal('suggested_fee', 10, 2)->default(0);
            $table->string('processing_time_label', 80)->default('1-2 Business Days');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('seller_shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->restrictOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('processing_time_label', 80)->default('1-2 Business Days');
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['seller_profile_id', 'shipping_method_id'], 'seller_shipping_method_unique');
            $table->index(['seller_profile_id', 'is_enabled']);
        });

        $now = now();
        foreach ([
            ['inside_dhaka', 'Inside Dhaka', 60, 'Same day', 10],
            ['outside_dhaka', 'Outside Dhaka', 120, '1-2 Business Days', 20],
            ['inside_rangpur', 'Inside Rangpur', 90, '1-2 Business Days', 30],
        ] as [$code, $name, $fee, $processing, $sort]) {
            DB::table('shipping_methods')->insert([
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'suggested_fee' => $fee,
                'processing_time_label' => $processing,
                'is_active' => true,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_shipping_methods');
        Schema::dropIfExists('shipping_methods');
    }
};

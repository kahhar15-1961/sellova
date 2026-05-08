<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 160);
            $table->string('method', 32);
            $table->string('driver', 32)->default('manual');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->json('supported_methods')->nullable();
            $table->string('checkout_url', 512)->nullable();
            $table->string('callback_url', 512)->nullable();
            $table->string('webhook_url', 512)->nullable();
            $table->string('public_key', 256)->nullable();
            $table->string('merchant_id', 256)->nullable();
            $table->longText('credentials_json')->nullable();
            $table->json('extra_json')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('label', 80)->nullable();
                $table->string('address_type', 24)->default('shipping');
                $table->string('recipient_name', 191);
                $table->string('phone', 40)->nullable();
                $table->text('address_line');
                $table->string('city', 120)->nullable();
                $table->string('region', 120)->nullable();
                $table->string('postal_code', 40)->nullable();
                $table->string('country', 120)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['user_id', 'address_type'], 'idx_user_addresses_user_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_addresses')) {
            Schema::drop('user_addresses');
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_comms_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('channel', 32)->default('webhook');
            $table->string('webhook_url', 512)->nullable();
            $table->string('email_to', 255)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->dateTime('last_tested_at', 6)->nullable();
            $table->json('config_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_comms_integrations');
    }
};

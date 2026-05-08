<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_notification_settings', static function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('provider', 32)->default('fcm');
            $table->string('fcm_project_id')->nullable();
            $table->string('fcm_client_email')->nullable();
            $table->text('fcm_private_key')->nullable();
            $table->string('android_channel_id')->default('sellova-default');
            $table->string('android_channel_name')->default('Sellova');
            $table->string('android_channel_description')->default('Order, wallet, and support alerts.');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_settings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->boolean('in_app_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('order_updates_enabled')->default(true);
                $table->boolean('promotion_enabled')->default(true);
                $table->timestamps();

                $table->unique('user_id', 'uq_user_notification_preferences_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_notification_preferences')) {
            Schema::drop('user_notification_preferences');
        }
    }
};


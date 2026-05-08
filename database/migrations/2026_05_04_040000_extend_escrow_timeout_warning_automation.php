<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrow_timeout_settings', static function (Blueprint $table): void {
            if (! Schema::hasColumn('escrow_timeout_settings', 'unpaid_order_warning_minutes')) {
                $table->unsignedInteger('unpaid_order_warning_minutes')->default(10)->after('unpaid_order_expiration_minutes');
            }
            if (! Schema::hasColumn('escrow_timeout_settings', 'seller_fulfillment_warning_hours')) {
                $table->unsignedInteger('seller_fulfillment_warning_hours')->default(2)->after('seller_fulfillment_deadline_hours');
            }
            if (! Schema::hasColumn('escrow_timeout_settings', 'escalation_warning_minutes')) {
                $table->unsignedInteger('escalation_warning_minutes')->default(60)->after('buyer_review_reminder_2_hours');
            }
        });

        Schema::table('orders', static function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'unpaid_reminder_at')) {
                $table->timestamp('unpaid_reminder_at')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('orders', 'seller_reminder_at')) {
                $table->timestamp('seller_reminder_at')->nullable()->after('seller_deadline_at');
            }
            if (! Schema::hasColumn('orders', 'escalation_warning_at')) {
                $table->timestamp('escalation_warning_at')->nullable()->after('escalation_at');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('escrow_timeout_settings')) {
            Schema::create('escrow_timeout_settings', static function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('unpaid_order_expiration_minutes')->default(30);
                $table->unsignedInteger('seller_fulfillment_deadline_hours')->default(24);
                $table->unsignedInteger('buyer_review_deadline_hours')->default(72);
                $table->unsignedInteger('buyer_review_reminder_1_hours')->default(24);
                $table->unsignedInteger('buyer_review_reminder_2_hours')->default(48);
                $table->unsignedInteger('seller_min_fulfillment_hours')->default(1);
                $table->unsignedInteger('seller_max_fulfillment_hours')->default(168);
                $table->unsignedInteger('buyer_min_review_hours')->default(1);
                $table->unsignedInteger('buyer_max_review_hours')->default(168);
                $table->boolean('auto_escalation_after_review_expiry')->default(true);
                $table->boolean('auto_cancel_unpaid_orders')->default(true);
                $table->boolean('auto_release_after_buyer_timeout')->default(false);
                $table->boolean('auto_create_dispute_on_timeout')->default(false);
                $table->boolean('dispute_review_queue_enabled')->default(true);
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('orders', static function (Blueprint $table): void {
            foreach ([
                'seller_deadline_at' => 'delivered_at',
                'buyer_review_expires_at' => 'buyer_review_started_at',
                'reminder_1_at' => 'buyer_review_expires_at',
                'reminder_2_at' => 'reminder_1_at',
                'escalation_at' => 'reminder_2_at',
                'auto_release_at' => 'escalation_at',
            ] as $column => $after) {
                if (! Schema::hasColumn('orders', $column)) {
                    $table->timestamp($column)->nullable()->after($after);
                }
            }
            if (! Schema::hasColumn('orders', 'timeout_policy_snapshot_json')) {
                $table->json('timeout_policy_snapshot_json')->nullable()->after('auto_release_at');
            }
        });

        if (! Schema::hasTable('escrow_timeout_events')) {
            Schema::create('escrow_timeout_events', static function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->unique();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('escrow_account_id')->nullable();
                $table->string('event_type', 96);
                $table->string('status', 32)->default('processed');
                $table->string('action_taken', 96)->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'event_type'], 'uq_timeout_events_order_type');
                $table->index(['event_type', 'status', 'scheduled_for'], 'idx_timeout_events_type_status_due');
            });
        }
    }
};


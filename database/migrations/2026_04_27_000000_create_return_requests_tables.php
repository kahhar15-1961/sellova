<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('return_requests')) {
            Schema::create('return_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('order_item_id')->nullable();
                $table->unsignedBigInteger('buyer_user_id');
                $table->unsignedBigInteger('seller_user_id')->nullable();
                $table->string('reason_code', 64);
                $table->text('notes')->nullable();
                $table->json('evidence_json')->nullable();
                $table->string('status', 32)->default('requested');
                $table->string('decision_note', 255)->nullable();
                $table->unsignedBigInteger('decided_by_user_id')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->index(['buyer_user_id', 'status'], 'idx_return_requests_buyer_status');
                $table->index(['seller_user_id', 'status'], 'idx_return_requests_seller_status');
                $table->index('order_id', 'idx_return_requests_order_id');
                $table->unique('uuid', 'uq_return_requests_uuid');
            });
        }

        if (! Schema::hasTable('return_request_events')) {
            Schema::create('return_request_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('return_request_id');
                $table->string('event_code', 64);
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['return_request_id', 'id'], 'idx_return_request_events_rrid_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_request_events')) {
            Schema::drop('return_request_events');
        }
        if (Schema::hasTable('return_requests')) {
            Schema::drop('return_requests');
        }
    }
};


<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('action_code', 128);
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');
            $table->json('proposed_payload_json');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('reason_code', 128)->nullable();
            $table->string('decision_reason', 1000)->nullable();
            $table->dateTime('requested_at', 6);
            $table->dateTime('decided_at', 6)->nullable();
            $table->timestamps(6);

            $table->index(['target_type', 'target_id', 'status'], 'idx_admin_approvals_target_status');
            $table->index(['requested_by_user_id', 'status'], 'idx_admin_approvals_requester_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_approvals');
    }
};

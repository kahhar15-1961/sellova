<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_escalation_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('queue_code', 64);
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');
            $table->string('status', 32)->default('open');
            $table->string('severity', 32)->default('high');
            $table->string('reason_code', 128)->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->dateTime('sla_breached_at', 6)->nullable();
            $table->dateTime('opened_at', 6)->nullable();
            $table->dateTime('acknowledged_at', 6)->nullable();
            $table->dateTime('resolved_at', 6)->nullable();
            $table->dateTime('last_notified_at', 6)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['queue_code', 'target_type', 'target_id'], 'uniq_admin_escalation_target');
            $table->index(['status', 'severity'], 'idx_admin_escalation_status_severity');
            $table->index('assigned_user_id', 'idx_admin_escalation_assignee');
            $table->index('opened_at', 'idx_admin_escalation_opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_escalation_incidents');
    }
};

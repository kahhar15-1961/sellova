<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_runbook_executions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('runbook_id');
            $table->unsignedBigInteger('started_by_user_id')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->string('status', 32)->default('in_progress');
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'runbook_id'], 'uniq_admin_runbook_execution_incident_runbook');
            $table->index(['incident_id', 'status'], 'idx_admin_runbook_execution_incident_status');
        });

        Schema::create('admin_runbook_step_executions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('execution_id');
            $table->unsignedBigInteger('runbook_step_id');
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('evidence_notes')->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['execution_id', 'runbook_step_id'], 'uniq_admin_runbook_step_execution');
            $table->index(['execution_id', 'status'], 'idx_admin_runbook_step_execution_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_runbook_step_executions');
        Schema::dropIfExists('admin_runbook_executions');
    }
};

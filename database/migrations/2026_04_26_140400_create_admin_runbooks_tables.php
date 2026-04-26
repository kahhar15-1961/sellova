<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_runbooks', function (Blueprint $table): void {
            $table->id();
            $table->string('queue_code', 64);
            $table->string('title', 160);
            $table->text('objective')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['queue_code', 'is_active'], 'idx_admin_runbooks_queue_active');
        });

        Schema::create('admin_runbook_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('runbook_id');
            $table->unsignedInteger('step_order');
            $table->text('instruction');
            $table->boolean('is_required')->default(true);
            $table->boolean('evidence_required')->default(false);
            $table->timestamps();

            $table->unique(['runbook_id', 'step_order'], 'uniq_admin_runbook_step_order');
            $table->index('runbook_id', 'idx_admin_runbook_steps_runbook');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_runbook_steps');
        Schema::dropIfExists('admin_runbooks');
    }
};

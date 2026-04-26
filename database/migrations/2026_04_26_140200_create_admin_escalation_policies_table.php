<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_escalation_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('queue_code', 64)->unique();
            $table->string('default_severity', 32)->default('high');
            $table->boolean('auto_assign_on_call')->default(true);
            $table->string('on_call_role_code', 64)->nullable();
            $table->unsignedInteger('ack_sla_minutes')->default(30);
            $table->unsignedInteger('resolve_sla_minutes')->default(240);
            $table->unsignedBigInteger('comms_integration_id')->nullable();
            $table->json('escalation_ladder_json')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_escalation_policies');
    }
};

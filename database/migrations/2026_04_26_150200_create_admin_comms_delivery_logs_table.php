<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_comms_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->string('event_type', 96);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('next_retry_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->json('request_payload_json')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'idx_admin_comms_logs_status_retry');
            $table->index('integration_id', 'idx_admin_comms_logs_integration');
            $table->index('incident_id', 'idx_admin_comms_logs_incident');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_comms_delivery_logs');
    }
};

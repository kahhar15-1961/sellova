<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_escalation_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event_type', 64);
            $table->json('payload_json')->nullable();
            $table->dateTime('created_at', 6);

            $table->index(['incident_id', 'id'], 'idx_admin_escalation_events_incident');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_escalation_events');
    }
};

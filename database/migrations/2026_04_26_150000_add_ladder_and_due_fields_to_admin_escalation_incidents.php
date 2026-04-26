<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_escalation_incidents', function (Blueprint $table): void {
            $table->dateTime('ack_due_at', 6)->nullable()->after('opened_at');
            $table->dateTime('resolve_due_at', 6)->nullable()->after('ack_due_at');
            $table->unsignedTinyInteger('current_ladder_level')->default(1)->after('severity');
            $table->dateTime('next_ladder_at', 6)->nullable()->after('resolve_due_at');
            $table->dateTime('last_ladder_triggered_at', 6)->nullable()->after('next_ladder_at');

            $table->index('ack_due_at', 'idx_admin_escalation_ack_due_at');
            $table->index('resolve_due_at', 'idx_admin_escalation_resolve_due_at');
            $table->index('next_ladder_at', 'idx_admin_escalation_next_ladder_at');
        });
    }

    public function down(): void
    {
        Schema::table('admin_escalation_incidents', function (Blueprint $table): void {
            $table->dropIndex('idx_admin_escalation_ack_due_at');
            $table->dropIndex('idx_admin_escalation_resolve_due_at');
            $table->dropIndex('idx_admin_escalation_next_ladder_at');
            $table->dropColumn([
                'ack_due_at',
                'resolve_due_at',
                'current_ladder_level',
                'next_ladder_at',
                'last_ladder_triggered_at',
            ]);
        });
    }
};

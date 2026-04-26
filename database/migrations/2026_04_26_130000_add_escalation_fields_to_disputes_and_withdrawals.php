<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispute_cases', function (Blueprint $table): void {
            $table->dateTime('escalated_at', 6)->nullable()->after('assigned_at');
            $table->string('escalation_reason', 255)->nullable()->after('escalated_at');
            $table->index('escalated_at', 'idx_dispute_cases_escalated_at');
        });

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->dateTime('escalated_at', 6)->nullable()->after('assigned_at');
            $table->string('escalation_reason', 255)->nullable()->after('escalated_at');
            $table->index('escalated_at', 'idx_withdrawal_requests_escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('dispute_cases', function (Blueprint $table): void {
            $table->dropIndex('idx_dispute_cases_escalated_at');
            $table->dropColumn(['escalated_at', 'escalation_reason']);
        });

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->dropIndex('idx_withdrawal_requests_escalated_at');
            $table->dropColumn(['escalated_at', 'escalation_reason']);
        });
    }
};

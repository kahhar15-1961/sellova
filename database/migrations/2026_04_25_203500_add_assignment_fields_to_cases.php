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
            if (! Schema::hasColumn('dispute_cases', 'assigned_to_user_id')) {
                $table->unsignedBigInteger('assigned_to_user_id')->nullable()->after('opened_by_user_id');
                $table->dateTime('assigned_at', 6)->nullable()->after('assigned_to_user_id');
                $table->index(['assigned_to_user_id', 'status'], 'idx_dispute_cases_assignee_status');
            }
        });

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('withdrawal_requests', 'assigned_to_user_id')) {
                $table->unsignedBigInteger('assigned_to_user_id')->nullable()->after('reviewed_by');
                $table->dateTime('assigned_at', 6)->nullable()->after('assigned_to_user_id');
                $table->index(['assigned_to_user_id', 'status'], 'idx_withdrawals_assignee_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispute_cases', function (Blueprint $table): void {
            if (Schema::hasColumn('dispute_cases', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
            if (Schema::hasColumn('dispute_cases', 'assigned_to_user_id')) {
                $table->dropIndex('idx_dispute_cases_assignee_status');
                $table->dropColumn('assigned_to_user_id');
            }
        });

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('withdrawal_requests', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
            if (Schema::hasColumn('withdrawal_requests', 'assigned_to_user_id')) {
                $table->dropIndex('idx_withdrawals_assignee_status');
                $table->dropColumn('assigned_to_user_id');
            }
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('kyc_verifications', 'assigned_to_user_id')) {
                $table->unsignedBigInteger('assigned_to_user_id')->nullable()->after('reviewed_by');
                $table->dateTime('assigned_at', 6)->nullable()->after('assigned_to_user_id');
                $table->index(['assigned_to_user_id', 'status'], 'idx_kyc_verifications_assignee_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kyc_verifications', function (Blueprint $table): void {
            if (Schema::hasColumn('kyc_verifications', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
            if (Schema::hasColumn('kyc_verifications', 'assigned_to_user_id')) {
                $table->dropIndex('idx_kyc_verifications_assignee_status');
                $table->dropColumn('assigned_to_user_id');
            }
        });
    }
};

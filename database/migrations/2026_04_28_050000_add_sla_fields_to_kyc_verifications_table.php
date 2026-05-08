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
            if (! Schema::hasColumn('kyc_verifications', 'sla_due_at')) {
                $table->dateTime('sla_due_at', 6)->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('kyc_verifications', 'sla_warning_sent_at')) {
                $table->dateTime('sla_warning_sent_at', 6)->nullable()->after('sla_due_at');
            }
            if (! Schema::hasColumn('kyc_verifications', 'escalated_at')) {
                $table->dateTime('escalated_at', 6)->nullable()->after('sla_warning_sent_at');
            }
            if (! Schema::hasColumn('kyc_verifications', 'escalation_reason')) {
                $table->string('escalation_reason', 255)->nullable()->after('escalated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kyc_verifications', function (Blueprint $table): void {
            if (Schema::hasColumn('kyc_verifications', 'escalation_reason')) {
                $table->dropColumn('escalation_reason');
            }
            if (Schema::hasColumn('kyc_verifications', 'escalated_at')) {
                $table->dropColumn('escalated_at');
            }
            if (Schema::hasColumn('kyc_verifications', 'sla_warning_sent_at')) {
                $table->dropColumn('sla_warning_sent_at');
            }
            if (Schema::hasColumn('kyc_verifications', 'sla_due_at')) {
                $table->dropColumn('sla_due_at');
            }
        });
    }
};

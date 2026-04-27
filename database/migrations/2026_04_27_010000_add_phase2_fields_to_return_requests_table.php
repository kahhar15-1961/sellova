<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('return_requests')) {
            return;
        }

        Schema::table('return_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('return_requests', 'rma_code')) {
                $table->string('rma_code', 64)->nullable()->after('uuid');
            }
            if (! Schema::hasColumn('return_requests', 'resolution_code')) {
                $table->string('resolution_code', 64)->nullable()->after('status');
            }
            if (! Schema::hasColumn('return_requests', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('requested_at');
            }
            if (! Schema::hasColumn('return_requests', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('sla_due_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('return_requests')) {
            return;
        }

        Schema::table('return_requests', function (Blueprint $table): void {
            foreach (['rma_code', 'resolution_code', 'sla_due_at', 'escalated_at'] as $column) {
                if (Schema::hasColumn('return_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};


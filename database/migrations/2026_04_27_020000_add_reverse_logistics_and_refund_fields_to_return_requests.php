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
            if (! Schema::hasColumn('return_requests', 'reverse_logistics_status')) {
                $table->string('reverse_logistics_status', 32)->nullable()->after('resolution_code');
            }
            if (! Schema::hasColumn('return_requests', 'return_tracking_url')) {
                $table->string('return_tracking_url', 500)->nullable()->after('reverse_logistics_status');
            }
            if (! Schema::hasColumn('return_requests', 'return_carrier')) {
                $table->string('return_carrier', 100)->nullable()->after('return_tracking_url');
            }
            if (! Schema::hasColumn('return_requests', 'refund_status')) {
                $table->string('refund_status', 32)->nullable()->after('return_carrier');
            }
            if (! Schema::hasColumn('return_requests', 'refund_amount')) {
                $table->decimal('refund_amount', 12, 4)->nullable()->after('refund_status');
            }
            if (! Schema::hasColumn('return_requests', 'refund_submitted_at')) {
                $table->timestamp('refund_submitted_at')->nullable()->after('refund_amount');
            }
            if (! Schema::hasColumn('return_requests', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('refund_submitted_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('return_requests')) {
            return;
        }

        Schema::table('return_requests', function (Blueprint $table): void {
            foreach ([
                'reverse_logistics_status',
                'return_tracking_url',
                'return_carrier',
                'refund_status',
                'refund_amount',
                'refund_submitted_at',
                'refunded_at',
            ] as $column) {
                if (Schema::hasColumn('return_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};


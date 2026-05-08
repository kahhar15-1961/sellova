<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            if (! Schema::hasColumn('promotions', 'daily_start_time')) {
                $table->string('daily_start_time', 5)->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('promotions', 'daily_end_time')) {
                $table->string('daily_end_time', 5)->nullable()->after('daily_start_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            foreach (['daily_end_time', 'daily_start_time'] as $column) {
                if (Schema::hasColumn('promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

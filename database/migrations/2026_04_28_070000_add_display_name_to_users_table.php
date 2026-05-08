<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'display_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('display_name', 191)->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'display_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('display_name');
            });
        }
    }
};

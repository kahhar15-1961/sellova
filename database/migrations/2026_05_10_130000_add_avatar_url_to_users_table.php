<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'avatar_url')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('avatar_url', 512)->nullable()->after('display_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar_url')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_url');
            });
        }
    }
};

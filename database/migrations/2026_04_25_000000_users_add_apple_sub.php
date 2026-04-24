<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'apple_sub')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('apple_sub', 128)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'apple_sub')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('apple_sub');
        });
    }
};

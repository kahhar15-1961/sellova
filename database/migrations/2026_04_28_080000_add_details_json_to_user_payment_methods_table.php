<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_payment_methods') && ! Schema::hasColumn('user_payment_methods', 'details_json')) {
            Schema::table('user_payment_methods', function (Blueprint $table): void {
                $table->json('details_json')->nullable()->after('subtitle');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_payment_methods') && Schema::hasColumn('user_payment_methods', 'details_json')) {
            Schema::table('user_payment_methods', function (Blueprint $table): void {
                $table->dropColumn('details_json');
            });
        }
    }
};

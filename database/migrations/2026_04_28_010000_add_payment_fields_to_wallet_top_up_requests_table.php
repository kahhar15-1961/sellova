<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_top_up_requests')) {
            Schema::table('wallet_top_up_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('wallet_top_up_requests', 'payment_method')) {
                    $table->string('payment_method', 32)->nullable()->after('requested_amount');
                }
                if (! Schema::hasColumn('wallet_top_up_requests', 'payment_reference')) {
                    $table->string('payment_reference', 191)->nullable()->after('payment_method');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_top_up_requests')) {
            Schema::table('wallet_top_up_requests', function (Blueprint $table): void {
                if (Schema::hasColumn('wallet_top_up_requests', 'payment_reference')) {
                    $table->dropColumn('payment_reference');
                }
                if (Schema::hasColumn('wallet_top_up_requests', 'payment_method')) {
                    $table->dropColumn('payment_method');
                }
            });
        }
    }
};

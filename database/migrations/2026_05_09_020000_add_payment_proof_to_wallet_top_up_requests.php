<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_top_up_requests') && ! Schema::hasColumn('wallet_top_up_requests', 'payment_proof_url')) {
            Schema::table('wallet_top_up_requests', static function (Blueprint $table): void {
                $table->string('payment_proof_url', 2048)->nullable()->after('payment_reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_top_up_requests') && Schema::hasColumn('wallet_top_up_requests', 'payment_proof_url')) {
            Schema::table('wallet_top_up_requests', static function (Blueprint $table): void {
                $table->dropColumn('payment_proof_url');
            });
        }
    }
};

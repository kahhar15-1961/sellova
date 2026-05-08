<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'promo_code')) {
                $table->string('promo_code', 64)->nullable()->after('net_amount');
            }

            if (! Schema::hasColumn('orders', 'shipping_method')) {
                $table->string('shipping_method', 32)->nullable()->after('promo_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'shipping_method')) {
                $table->dropColumn('shipping_method');
            }

            if (Schema::hasColumn('orders', 'promo_code')) {
                $table->dropColumn('promo_code');
            }
        });
    }
};

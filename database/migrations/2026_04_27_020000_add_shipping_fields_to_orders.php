<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'courier_company')) {
                $table->string('courier_company', 191)->nullable()->after('shipping_method');
            }
            if (! Schema::hasColumn('orders', 'tracking_id')) {
                $table->string('tracking_id', 191)->nullable()->after('courier_company');
            }
            if (! Schema::hasColumn('orders', 'tracking_url')) {
                $table->string('tracking_url', 512)->nullable()->after('tracking_id');
            }
            if (! Schema::hasColumn('orders', 'shipping_note')) {
                $table->text('shipping_note')->nullable()->after('tracking_url');
            }
            if (! Schema::hasColumn('orders', 'shipped_at')) {
                $table->dateTime('shipped_at', 6)->nullable()->after('shipping_note');
            }
            if (! Schema::hasColumn('orders', 'delivered_at')) {
                $table->dateTime('delivered_at', 6)->nullable()->after('shipped_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            foreach ([
                'delivered_at',
                'shipped_at',
                'shipping_note',
                'tracking_url',
                'tracking_id',
                'courier_company',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

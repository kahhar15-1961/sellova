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
            if (! Schema::hasColumn('orders', 'shipping_address_id')) {
                $table->string('shipping_address_id', 191)->nullable()->after('shipping_method');
            }
            if (! Schema::hasColumn('orders', 'shipping_recipient_name')) {
                $table->string('shipping_recipient_name', 191)->nullable()->after('shipping_address_id');
            }
            if (! Schema::hasColumn('orders', 'shipping_address_line')) {
                $table->text('shipping_address_line')->nullable()->after('shipping_recipient_name');
            }
            if (! Schema::hasColumn('orders', 'shipping_phone')) {
                $table->string('shipping_phone', 32)->nullable()->after('shipping_address_line');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            foreach ([
                'shipping_phone',
                'shipping_address_line',
                'shipping_recipient_name',
                'shipping_address_id',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

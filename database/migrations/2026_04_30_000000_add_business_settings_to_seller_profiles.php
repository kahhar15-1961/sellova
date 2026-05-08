<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('seller_profiles', 'inside_dhaka_label')) {
                $table->string('inside_dhaka_label')->nullable()->after('banner_image_url');
            }
            if (! Schema::hasColumn('seller_profiles', 'inside_dhaka_fee')) {
                $table->decimal('inside_dhaka_fee', 10, 2)->nullable()->after('inside_dhaka_label');
            }
            if (! Schema::hasColumn('seller_profiles', 'outside_dhaka_label')) {
                $table->string('outside_dhaka_label')->nullable()->after('inside_dhaka_fee');
            }
            if (! Schema::hasColumn('seller_profiles', 'outside_dhaka_fee')) {
                $table->decimal('outside_dhaka_fee', 10, 2)->nullable()->after('outside_dhaka_label');
            }
            if (! Schema::hasColumn('seller_profiles', 'cash_on_delivery_enabled')) {
                $table->boolean('cash_on_delivery_enabled')->nullable()->after('outside_dhaka_fee');
            }
            if (! Schema::hasColumn('seller_profiles', 'processing_time_label')) {
                $table->string('processing_time_label')->nullable()->after('cash_on_delivery_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            foreach ([
                'processing_time_label',
                'cash_on_delivery_enabled',
                'outside_dhaka_fee',
                'outside_dhaka_label',
                'inside_dhaka_fee',
                'inside_dhaka_label',
            ] as $column) {
                if (Schema::hasColumn('seller_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('seller_profiles', 'contact_email')) {
                $table->string('contact_email', 191)->nullable()->after('banner_image_url');
            }
            if (! Schema::hasColumn('seller_profiles', 'contact_phone')) {
                $table->string('contact_phone', 40)->nullable()->after('contact_email');
            }
            if (! Schema::hasColumn('seller_profiles', 'address_line')) {
                $table->string('address_line', 255)->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('seller_profiles', 'city')) {
                $table->string('city', 120)->nullable()->after('address_line');
            }
            if (! Schema::hasColumn('seller_profiles', 'region')) {
                $table->string('region', 120)->nullable()->after('city');
            }
            if (! Schema::hasColumn('seller_profiles', 'postal_code')) {
                $table->string('postal_code', 40)->nullable()->after('region');
            }
            if (! Schema::hasColumn('seller_profiles', 'country')) {
                $table->string('country', 120)->nullable()->after('postal_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            foreach ([
                'country',
                'postal_code',
                'region',
                'city',
                'address_line',
                'contact_phone',
                'contact_email',
            ] as $column) {
                if (Schema::hasColumn('seller_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

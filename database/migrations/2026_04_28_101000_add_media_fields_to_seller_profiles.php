<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('seller_profiles', 'store_logo_url')) {
                $table->string('store_logo_url', 512)->nullable()->after('store_status');
            }
            if (! Schema::hasColumn('seller_profiles', 'banner_image_url')) {
                $table->string('banner_image_url', 512)->nullable()->after('store_logo_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('seller_profiles', 'banner_image_url')) {
                $table->dropColumn('banner_image_url');
            }
            if (Schema::hasColumn('seller_profiles', 'store_logo_url')) {
                $table->dropColumn('store_logo_url');
            }
        });
    }
};

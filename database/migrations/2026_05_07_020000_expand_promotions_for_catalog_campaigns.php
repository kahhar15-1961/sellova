<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            if (! Schema::hasColumn('promotions', 'campaign_type')) {
                $table->string('campaign_type', 32)->default('coupon')->after('badge');
            }
            if (! Schema::hasColumn('promotions', 'scope_type')) {
                $table->string('scope_type', 32)->default('all')->after('campaign_type');
            }
            if (! Schema::hasColumn('promotions', 'target_product_ids')) {
                $table->json('target_product_ids')->nullable()->after('scope_type');
            }
            if (! Schema::hasColumn('promotions', 'target_seller_profile_ids')) {
                $table->json('target_seller_profile_ids')->nullable()->after('target_product_ids');
            }
            if (! Schema::hasColumn('promotions', 'target_category_ids')) {
                $table->json('target_category_ids')->nullable()->after('target_seller_profile_ids');
            }
            if (! Schema::hasColumn('promotions', 'target_product_types')) {
                $table->json('target_product_types')->nullable()->after('target_category_ids');
            }
            if (! Schema::hasColumn('promotions', 'priority')) {
                $table->unsignedInteger('priority')->default(100)->after('usage_limit');
            }
            if (! Schema::hasColumn('promotions', 'marketing_channel')) {
                $table->string('marketing_channel', 64)->nullable()->after('priority');
            }
            if (! Schema::hasColumn('promotions', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('marketing_channel')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            if (Schema::hasColumn('promotions', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }

            foreach ([
                'marketing_channel',
                'priority',
                'target_product_types',
                'target_category_ids',
                'target_seller_profile_ids',
                'target_product_ids',
                'scope_type',
                'campaign_type',
            ] as $column) {
                if (Schema::hasColumn('promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

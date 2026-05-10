<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'seller_reply')) {
                $table->text('seller_reply')->nullable()->after('comment');
            }

            if (! Schema::hasColumn('reviews', 'seller_replied_at')) {
                $table->dateTime('seller_replied_at', 6)->nullable()->after('seller_reply');
            }

            if (! Schema::hasColumn('reviews', 'helpful_count')) {
                $table->unsignedInteger('helpful_count')->default(0)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            if (Schema::hasColumn('reviews', 'helpful_count')) {
                $table->dropColumn('helpful_count');
            }

            if (Schema::hasColumn('reviews', 'seller_replied_at')) {
                $table->dropColumn('seller_replied_at');
            }

            if (Schema::hasColumn('reviews', 'seller_reply')) {
                $table->dropColumn('seller_reply');
            }
        });
    }
};

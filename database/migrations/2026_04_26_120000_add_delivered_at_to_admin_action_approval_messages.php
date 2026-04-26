<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_approval_messages', function (Blueprint $table): void {
            $table->dateTime('delivered_at', 6)->nullable()->after('created_at');
        });

        DB::table('admin_action_approval_messages')
            ->whereNull('delivered_at')
            ->update(['delivered_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('admin_action_approval_messages', function (Blueprint $table): void {
            $table->dropColumn('delivered_at');
        });
    }
};

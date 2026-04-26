<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'attachment_url')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->string('attachment_url', 512)->nullable()->after('body');
                $table->string('attachment_name', 191)->nullable()->after('attachment_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'attachment_url')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->dropColumn(['attachment_url', 'attachment_name']);
            });
        }
    }
};


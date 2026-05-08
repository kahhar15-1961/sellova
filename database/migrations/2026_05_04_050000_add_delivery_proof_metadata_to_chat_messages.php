<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_messages', 'receiver_user_id')) {
                $table->unsignedBigInteger('receiver_user_id')->nullable()->after('sender_user_id');
            }
            if (! Schema::hasColumn('chat_messages', 'sender_role')) {
                $table->string('sender_role', 32)->nullable()->after('receiver_user_id');
            }
            if (! Schema::hasColumn('chat_messages', 'attachment_type')) {
                $table->string('attachment_type', 32)->nullable()->after('attachment_name');
            }
            if (! Schema::hasColumn('chat_messages', 'attachment_mime')) {
                $table->string('attachment_mime', 191)->nullable()->after('attachment_type');
            }
            if (! Schema::hasColumn('chat_messages', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table): void {
            foreach (['attachment_size', 'attachment_mime', 'attachment_type', 'sender_role', 'receiver_user_id'] as $column) {
                if (Schema::hasColumn('chat_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

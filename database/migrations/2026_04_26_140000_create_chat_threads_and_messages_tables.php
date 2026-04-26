<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_threads')) {
            Schema::create('chat_threads', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->string('kind', 32)->default('order'); // order|support
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('buyer_user_id');
                $table->unsignedBigInteger('seller_user_id')->nullable();
                $table->string('subject', 191)->nullable();
                $table->string('status', 32)->default('open');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->unique('uuid', 'uq_chat_threads_uuid');
                $table->index(['buyer_user_id', 'last_message_at'], 'idx_chat_threads_buyer_last_message');
                $table->index(['seller_user_id', 'last_message_at'], 'idx_chat_threads_seller_last_message');
                $table->index(['order_id', 'kind'], 'idx_chat_threads_order_kind');
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->unsignedBigInteger('thread_id');
                $table->unsignedBigInteger('sender_user_id');
                $table->text('body');
                $table->timestamps();

                $table->unique('uuid', 'uq_chat_messages_uuid');
                $table->index(['thread_id', 'id'], 'idx_chat_messages_thread_id');
                $table->index('sender_user_id', 'idx_chat_messages_sender_user_id');
            });
        }

        if (! Schema::hasTable('chat_thread_reads')) {
            Schema::create('chat_thread_reads', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('thread_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['thread_id', 'user_id'], 'uq_chat_thread_reads_thread_user');
                $table->index('user_id', 'idx_chat_thread_reads_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_thread_reads')) {
            Schema::drop('chat_thread_reads');
        }
        if (Schema::hasTable('chat_messages')) {
            Schema::drop('chat_messages');
        }
        if (Schema::hasTable('chat_threads')) {
            Schema::drop('chat_threads');
        }
    }
};


<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_approval_thread_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('approval_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('last_read_message_id');
            $table->timestamps();

            $table->unique(['approval_id', 'user_id'], 'uniq_admin_approval_thread_reads');
            $table->index(['approval_id', 'last_read_message_id'], 'idx_admin_approval_thread_reads_approval');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_approval_thread_reads');
    }
};

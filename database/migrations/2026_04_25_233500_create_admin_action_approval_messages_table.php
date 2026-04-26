<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_approval_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('approval_id');
            $table->unsignedBigInteger('author_user_id');
            $table->text('message');
            $table->dateTime('created_at', 6);

            $table->index(['approval_id', 'id'], 'idx_admin_approval_messages_approval_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_approval_messages');
    }
};

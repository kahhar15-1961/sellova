<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_helpful_votes')) {
            return;
        }

        Schema::create('review_helpful_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['review_id', 'user_id'], 'uq_review_helpful_vote_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_helpful_votes');
    }
};

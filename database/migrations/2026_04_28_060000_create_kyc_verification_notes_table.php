<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verification_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('kyc_verification_id')->constrained('kyc_verifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_private')->default(true);
            $table->text('note');
            $table->timestamps();
            $table->index(['kyc_verification_id', 'created_at'], 'idx_kyc_verification_notes_case_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verification_notes');
    }
};

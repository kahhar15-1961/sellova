<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_on_call_rotations', function (Blueprint $table): void {
            $table->id();
            $table->string('role_code', 64);
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('weekday')->default(0);
            $table->unsignedTinyInteger('start_hour')->default(0);
            $table->unsignedTinyInteger('end_hour')->default(23);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['role_code', 'weekday', 'is_active'], 'idx_admin_on_call_role_day');
            $table->index(['user_id', 'is_active'], 'idx_admin_on_call_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_on_call_rotations');
    }
};

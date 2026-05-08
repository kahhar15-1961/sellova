<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('withdrawal_settings')) {
            Schema::create('withdrawal_settings', static function (Blueprint $table): void {
                $table->id();
                $table->decimal('minimum_withdrawal_amount', 12, 4)->default(500);
                $table->char('currency', 3)->default('BDT');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_settings');
    }
};

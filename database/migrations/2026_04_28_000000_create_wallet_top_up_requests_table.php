<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_top_up_requests')) {
            Schema::create('wallet_top_up_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable()->unique('uq_wallet_top_up_requests_uuid');
                $table->string('idempotency_key', 191)->unique('uq_wallet_top_up_requests_idempotency_key');
                $table->unsignedBigInteger('wallet_id');
                $table->unsignedBigInteger('requested_by_user_id');
                $table->string('status', 32)->default('requested');
                $table->decimal('requested_amount', 20, 4);
                $table->string('currency', 3);
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('ledger_batch_id')->nullable();
                $table->timestamps();

                $table->index(['wallet_id', 'status'], 'idx_wallet_top_up_requests_wallet_status');
                $table->index(['requested_by_user_id', 'status'], 'idx_wallet_top_up_requests_user_status');
                $table->index(['ledger_batch_id'], 'idx_wallet_top_up_requests_ledger_batch_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_top_up_requests')) {
            Schema::drop('wallet_top_up_requests');
        }
    }
};

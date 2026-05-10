<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'escrow_status')) {
                $table->string('escrow_status', 32)->nullable()->after('status');
                $table->index(['escrow_status', 'status'], 'idx_orders_escrow_status');
            }
            if (! Schema::hasColumn('orders', 'escrow_amount')) {
                $table->decimal('escrow_amount', 14, 4)->default(0)->after('fee_amount');
            }
            if (! Schema::hasColumn('orders', 'escrow_fee')) {
                $table->decimal('escrow_fee', 14, 4)->default(0)->after('escrow_amount');
            }
            if (! Schema::hasColumn('orders', 'escrow_started_at')) {
                $table->timestamp('escrow_started_at')->nullable()->after('placed_at');
            }
            if (! Schema::hasColumn('orders', 'escrow_expires_at')) {
                $table->timestamp('escrow_expires_at')->nullable()->after('escrow_started_at');
                $table->index(['escrow_expires_at'], 'idx_orders_escrow_expires_at');
            }
            if (! Schema::hasColumn('orders', 'escrow_released_at')) {
                $table->timestamp('escrow_released_at')->nullable()->after('escrow_expires_at');
            }
            if (! Schema::hasColumn('orders', 'escrow_auto_release_at')) {
                $table->timestamp('escrow_auto_release_at')->nullable()->after('escrow_released_at');
            }
            if (! Schema::hasColumn('orders', 'escrow_release_method')) {
                $table->string('escrow_release_method', 32)->nullable()->after('escrow_auto_release_at');
            }
            if (! Schema::hasColumn('orders', 'dispute_deadline_at')) {
                $table->timestamp('dispute_deadline_at')->nullable()->after('escrow_release_method');
            }
            if (! Schema::hasColumn('orders', 'delivery_deadline_at')) {
                $table->timestamp('delivery_deadline_at')->nullable()->after('dispute_deadline_at');
            }
            if (! Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status', 32)->nullable()->after('delivery_deadline_at');
                $table->index(['delivery_status'], 'idx_orders_delivery_status');
            }
            if (! Schema::hasColumn('orders', 'delivery_note')) {
                $table->text('delivery_note')->nullable()->after('delivery_status');
            }
            if (! Schema::hasColumn('orders', 'delivery_version')) {
                $table->string('delivery_version', 32)->nullable()->after('delivery_note');
            }
            if (! Schema::hasColumn('orders', 'delivery_files_count')) {
                $table->unsignedInteger('delivery_files_count')->default(0)->after('delivery_version');
            }
            if (! Schema::hasColumn('orders', 'buyer_confirmed_at')) {
                $table->timestamp('buyer_confirmed_at')->nullable()->after('delivery_files_count');
            }
        });

        Schema::table('escrow_accounts', static function (Blueprint $table): void {
            if (! Schema::hasColumn('escrow_accounts', 'escrow_fee')) {
                $table->decimal('escrow_fee', 14, 4)->default(0)->after('held_amount');
            }
            if (! Schema::hasColumn('escrow_accounts', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('held_at');
            }
            if (! Schema::hasColumn('escrow_accounts', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('started_at');
                $table->index(['expires_at'], 'idx_escrow_accounts_expires_at');
            }
            if (! Schema::hasColumn('escrow_accounts', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('escrow_accounts', 'auto_release_at')) {
                $table->timestamp('auto_release_at')->nullable()->after('released_at');
            }
            if (! Schema::hasColumn('escrow_accounts', 'release_method')) {
                $table->string('release_method', 32)->nullable()->after('auto_release_at');
            }
            if (! Schema::hasColumn('escrow_accounts', 'dispute_deadline_at')) {
                $table->timestamp('dispute_deadline_at')->nullable()->after('release_method');
            }
            if (! Schema::hasColumn('escrow_accounts', 'delivery_deadline_at')) {
                $table->timestamp('delivery_deadline_at')->nullable()->after('dispute_deadline_at');
            }
        });

        if (! Schema::hasTable('digital_deliveries')) {
            Schema::create('digital_deliveries', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('uq_digital_deliveries_uuid');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('seller_user_id');
                $table->unsignedBigInteger('buyer_user_id');
                $table->string('status', 32)->default('pending');
                $table->string('version', 32)->nullable();
                $table->string('external_url', 1000)->nullable();
                $table->text('delivery_note')->nullable();
                $table->unsignedInteger('files_count')->default(0);
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('buyer_confirmed_at')->nullable();
                $table->timestamp('revision_requested_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status'], 'idx_digital_deliveries_order_status');
                $table->index(['seller_user_id', 'status'], 'idx_digital_deliveries_seller_status');
            });
        }

        if (! Schema::hasTable('digital_delivery_files')) {
            Schema::create('digital_delivery_files', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('uq_digital_delivery_files_uuid');
                $table->unsignedBigInteger('digital_delivery_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('uploaded_by_user_id');
                $table->string('disk', 32)->default('local');
                $table->string('path', 1000);
                $table->string('original_name', 255);
                $table->string('mime_type', 191)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('visibility', 32)->default('escrow');
                $table->string('scan_status', 32)->default('pending');
                $table->timestamp('scan_completed_at')->nullable();
                $table->timestamp('downloaded_at')->nullable();
                $table->timestamps();

                $table->index(['digital_delivery_id', 'order_id'], 'idx_delivery_files_delivery_order');
                $table->index(['order_id', 'visibility'], 'idx_delivery_files_order_visibility');
            });
        }

        if (! Schema::hasTable('order_message_attachments')) {
            Schema::create('order_message_attachments', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('uq_order_message_attachments_uuid');
                $table->unsignedBigInteger('chat_message_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('uploaded_by_user_id');
                $table->string('disk', 32)->default('local');
                $table->string('path', 1000);
                $table->string('original_name', 255);
                $table->string('mime_type', 191)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('attachment_kind', 32)->default('file');
                $table->string('visibility', 32)->default('escrow');
                $table->string('scan_status', 32)->default('pending');
                $table->timestamp('scan_completed_at')->nullable();
                $table->timestamps();

                $table->index(['chat_message_id'], 'idx_order_message_attachments_message');
                $table->index(['order_id', 'visibility'], 'idx_order_message_attachments_order_visibility');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_message_attachments')) {
            Schema::drop('order_message_attachments');
        }

        if (Schema::hasTable('digital_delivery_files')) {
            Schema::drop('digital_delivery_files');
        }

        if (Schema::hasTable('digital_deliveries')) {
            Schema::drop('digital_deliveries');
        }

        Schema::table('escrow_accounts', static function (Blueprint $table): void {
            foreach ([
                'delivery_deadline_at',
                'dispute_deadline_at',
                'release_method',
                'auto_release_at',
                'released_at',
                'expires_at',
                'started_at',
                'escrow_fee',
            ] as $column) {
                if (Schema::hasColumn('escrow_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', static function (Blueprint $table): void {
            foreach ([
                'buyer_confirmed_at',
                'delivery_files_count',
                'delivery_version',
                'delivery_note',
                'delivery_status',
                'delivery_deadline_at',
                'dispute_deadline_at',
                'escrow_release_method',
                'escrow_auto_release_at',
                'escrow_released_at',
                'escrow_expires_at',
                'escrow_started_at',
                'escrow_fee',
                'escrow_amount',
                'escrow_status',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

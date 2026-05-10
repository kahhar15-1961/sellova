<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'type')) {
                $table->string('type', 128)->nullable()->after('channel');
            }
            if (! Schema::hasColumn('notifications', 'title')) {
                $table->string('title', 191)->nullable()->after('template_code');
            }
            if (! Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable()->after('title');
            }
            if (! Schema::hasColumn('notifications', 'icon')) {
                $table->string('icon', 64)->nullable()->after('message');
            }
            if (! Schema::hasColumn('notifications', 'color')) {
                $table->string('color', 32)->nullable()->after('icon');
            }
            if (! Schema::hasColumn('notifications', 'action_url')) {
                $table->string('action_url', 2048)->nullable()->after('color');
            }
            if (! Schema::hasColumn('notifications', 'metadata_json')) {
                $table->json('metadata_json')->nullable()->after('payload_json');
            }
            if (! Schema::hasColumn('notifications', 'user_role')) {
                $table->string('user_role', 32)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('notifications', 'priority')) {
                $table->string('priority', 16)->nullable()->after('user_role');
            }
        });

        try {
            DB::statement(<<<'SQL'
                UPDATE notifications
                SET
                    type = COALESCE(NULLIF(type, ''), NULLIF(template_code, ''), channel),
                    title = COALESCE(
                        NULLIF(title, ''),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.title')), 'null'),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.subject')), 'null'),
                        NULLIF(template_code, '')
                    ),
                    message = COALESCE(
                        NULLIF(message, ''),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.body')), 'null'),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.message')), 'null'),
                        ''
                    ),
                    icon = COALESCE(NULLIF(icon, ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.icon')), 'null')),
                    color = COALESCE(NULLIF(color, ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.color')), 'null')),
                    action_url = COALESCE(
                        NULLIF(action_url, ''),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.href')), 'null'),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.action_url')), 'null')
                    ),
                    metadata_json = COALESCE(metadata_json, payload_json),
                    priority = COALESCE(
                        NULLIF(priority, ''),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.priority')), 'null'),
                        'normal'
                    ),
                    user_role = COALESCE(
                        NULLIF(user_role, ''),
                        CASE
                            WHEN JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.role')) IN ('buyer', 'seller', 'all') THEN JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.role'))
                            WHEN COALESCE(
                                NULLIF(action_url, ''),
                                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.href')), 'null'),
                                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.action_url')), 'null'),
                                ''
                            ) LIKE '/seller/%' THEN 'seller'
                            ELSE 'buyer'
                        END
                    )
                SQL);
        } catch (\Throwable) {
            // Older local schemas may not support JSON_EXTRACT syntax identically; runtime normalization still applies.
        }

        try {
            DB::statement('CREATE INDEX idx_notifications_user_role_channel_read_created ON notifications (user_id, user_role, channel, read_at, created_at)');
        } catch (\Throwable) {
            // Index may already exist.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        try {
            DB::statement('DROP INDEX idx_notifications_user_role_channel_read_created ON notifications');
        } catch (\Throwable) {
            // Ignore when the index does not exist.
        }

        Schema::table('notifications', function (Blueprint $table): void {
            foreach (['type', 'title', 'message', 'icon', 'color', 'action_url', 'metadata_json', 'user_role', 'priority'] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

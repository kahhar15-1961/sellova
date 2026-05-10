<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE kyc_verifications MODIFY status ENUM('not_submitted','draft','submitted','under_review','third_party_pending','approved','verified','rejected','expired','resubmission_required') NOT NULL DEFAULT 'draft'");
        DB::statement('ALTER TABLE kyc_verifications MODIFY submitted_at DATETIME(6) NULL');

        Schema::table('kyc_verifications', static function (Blueprint $table): void {
            if (! Schema::hasColumn('kyc_verifications', 'submitted_by_user_id')) {
                $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('seller_profile_id');
            }
            if (! Schema::hasColumn('kyc_verifications', 'provider_id')) {
                $table->unsignedBigInteger('provider_id')->nullable()->after('provider_ref');
            }
            if (! Schema::hasColumn('kyc_verifications', 'provider_session_id')) {
                $table->string('provider_session_id', 191)->nullable()->after('provider_id');
            }
            if (! Schema::hasColumn('kyc_verifications', 'provider_session_url')) {
                $table->string('provider_session_url', 2048)->nullable()->after('provider_session_id');
            }
            if (! Schema::hasColumn('kyc_verifications', 'provider_result_json')) {
                $table->json('provider_result_json')->nullable()->after('provider_session_url');
            }
            if (! Schema::hasColumn('kyc_verifications', 'personal_info_encrypted')) {
                $table->longText('personal_info_encrypted')->nullable()->after('status');
            }
            if (! Schema::hasColumn('kyc_verifications', 'business_info_encrypted')) {
                $table->longText('business_info_encrypted')->nullable()->after('personal_info_encrypted');
            }
            if (! Schema::hasColumn('kyc_verifications', 'bank_info_encrypted')) {
                $table->longText('bank_info_encrypted')->nullable()->after('business_info_encrypted');
            }
            if (! Schema::hasColumn('kyc_verifications', 'address_info_encrypted')) {
                $table->longText('address_info_encrypted')->nullable()->after('bank_info_encrypted');
            }
            if (! Schema::hasColumn('kyc_verifications', 'risk_level')) {
                $table->string('risk_level', 32)->nullable()->after('provider_result_json');
            }
            if (! Schema::hasColumn('kyc_verifications', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('submitted_at');
            }
        });

        DB::statement(
            "ALTER TABLE kyc_documents MODIFY doc_type ENUM(
                'id_front','id_back','selfie','business_license','address_proof',
                'nid_front','nid_back','nid_selfie','license_front','license_back','license_selfie',
                'passport_page','passport_selfie','trade_license','tax_vat','bank_statement',
                'bank_account_proof','address_verification','face_verification'
            ) NOT NULL"
        );

        Schema::table('kyc_documents', static function (Blueprint $table): void {
            if (! Schema::hasColumn('kyc_documents', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id')->unique();
            }
            if (! Schema::hasColumn('kyc_documents', 'original_name')) {
                $table->string('original_name', 191)->nullable()->after('storage_path');
            }
            if (! Schema::hasColumn('kyc_documents', 'mime_type')) {
                $table->string('mime_type', 120)->nullable()->after('original_name');
            }
            if (! Schema::hasColumn('kyc_documents', 'file_size')) {
                $table->unsignedBigInteger('file_size')->default(0)->after('mime_type');
            }
            if (! Schema::hasColumn('kyc_documents', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('status');
            }
        });

        if (! Schema::hasTable('kyc_verification_providers')) {
            Schema::create('kyc_verification_providers', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code', 64)->unique();
                $table->string('name', 191);
                $table->string('mode', 32)->default('mock');
                $table->boolean('is_active')->default(false);
                $table->json('config_json')->nullable();
                $table->text('webhook_secret_encrypted')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('kyc_status_histories')) {
            Schema::create('kyc_status_histories', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('kyc_verification_id');
                $table->string('from_status', 64)->nullable();
                $table->string('to_status', 64);
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('reason_code', 128)->nullable();
                $table->text('note')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();
                $table->index(['kyc_verification_id', 'created_at'], 'idx_kyc_status_histories_kyc_created');
            });
        }

        if (! Schema::hasTable('kyc_verification_logs')) {
            Schema::create('kyc_verification_logs', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('kyc_verification_id')->nullable();
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->string('direction', 32);
                $table->string('event_type', 128);
                $table->string('signature_status', 32)->nullable();
                $table->json('payload_json')->nullable();
                $table->json('response_json')->nullable();
                $table->timestamps();
                $table->index(['provider_id', 'created_at'], 'idx_kyc_logs_provider_created');
            });
        }

        if (! Schema::hasTable('kyc_settings')) {
            Schema::create('kyc_settings', static function (Blueprint $table): void {
                $table->id();
                $table->string('seller_type', 64)->default('default')->unique();
                $table->boolean('require_for_product_publish')->default(true);
                $table->boolean('require_for_withdrawal')->default(true);
                $table->json('required_documents_json')->nullable();
                $table->unsignedInteger('expiry_months')->default(12);
                $table->timestamps();
            });
        }

        DB::table('kyc_verification_providers')->updateOrInsert(
            ['code' => 'mock'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Internal Mock Verification',
                'mode' => 'mock',
                'is_active' => true,
                'config_json' => json_encode(['providers_supported' => ['stripe_identity', 'sumsub', 'onfido', 'veriff', 'persona', 'shufti_pro']]),
                'webhook_secret_encrypted' => encrypt('local-kyc-webhook-secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('kyc_settings')->updateOrInsert(
            ['seller_type' => 'default'],
            [
                'require_for_product_publish' => true,
                'require_for_withdrawal' => true,
                'required_documents_json' => json_encode(['id_front', 'trade_license', 'tax_vat', 'bank_account_proof', 'address_verification']),
                'expiry_months' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_settings');
        Schema::dropIfExists('kyc_verification_logs');
        Schema::dropIfExists('kyc_status_histories');
        Schema::dropIfExists('kyc_verification_providers');
    }
};

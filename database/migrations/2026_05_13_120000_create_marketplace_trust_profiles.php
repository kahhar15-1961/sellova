<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('buyer_profiles')) {
            Schema::create('buyer_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('display_name')->nullable();
                $table->string('avatar_url', 512)->nullable();
                $table->string('verification_status', 40)->default('unverified');
                $table->string('kyc_status', 40)->default('not_submitted');
                $table->unsignedTinyInteger('communication_rating')->nullable();
                $table->unsignedTinyInteger('payment_reliability_rating')->nullable();
                $table->unsignedTinyInteger('cooperation_rating')->nullable();
                $table->json('public_badges_json')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('seller_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('seller_profiles', 'kyc_status')) {
                $table->string('kyc_status', 40)->default('not_submitted')->after('verification_status');
            }
            if (! Schema::hasColumn('seller_profiles', 'store_policies_json')) {
                $table->json('store_policies_json')->nullable()->after('processing_time_label');
            }
            if (! Schema::hasColumn('seller_profiles', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable()->after('store_policies_json');
            }
        });

        if (! Schema::hasTable('marketplace_reviews')) {
            Schema::create('marketplace_reviews', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
                $table->string('reviewer_role', 20);
                $table->unsignedBigInteger('reviewed_id');
                $table->string('reviewed_role', 20);
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->string('feedback_type', 20)->default('neutral');
                $table->string('title', 160)->nullable();
                $table->text('comment')->nullable();
                $table->json('tags')->nullable();
                $table->json('review_images')->nullable();
                $table->boolean('is_verified_order')->default(false);
                $table->string('status', 20)->default('visible');
                $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('moderated_at')->nullable();
                $table->text('moderation_note')->nullable();
                $table->timestamps();

                $table->index(['reviewed_role', 'reviewed_id', 'status', 'created_at'], 'idx_marketplace_reviews_subject');
                $table->index(['reviewer_id', 'reviewer_role', 'created_at'], 'idx_marketplace_reviews_reviewer');
                $table->index(['feedback_type', 'rating'], 'idx_marketplace_reviews_feedback');
            });
        }

        if (! Schema::hasTable('review_ratings')) {
            Schema::create('review_ratings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('marketplace_review_id')->constrained('marketplace_reviews')->cascadeOnDelete();
                $table->string('category', 80);
                $table->unsignedTinyInteger('rating');
                $table->timestamps();

                $table->unique(['marketplace_review_id', 'category'], 'uq_review_ratings_review_category');
            });
        }

        if (! Schema::hasTable('review_reports')) {
            Schema::create('review_reports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('marketplace_review_id')->constrained('marketplace_reviews')->cascadeOnDelete();
                $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
                $table->string('reason_code', 80);
                $table->text('details')->nullable();
                $table->string('status', 20)->default('open');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['marketplace_review_id', 'reporter_id'], 'uq_review_reports_review_reporter');
            });
        }

        if (! Schema::hasTable('trust_score_snapshots')) {
            Schema::create('trust_score_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->string('profile_type', 20);
                $table->unsignedBigInteger('profile_id');
                $table->unsignedTinyInteger('score');
                $table->string('label', 40);
                $table->json('factors_json');
                $table->timestamp('calculated_at');
                $table->timestamps();

                $table->index(['profile_type', 'profile_id', 'calculated_at'], 'idx_trust_score_snapshots_profile');
            });
        }

        if (! Schema::hasTable('profile_view_logs')) {
            Schema::create('profile_view_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('viewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('viewer_role', 20)->nullable();
                $table->string('profile_type', 20);
                $table->unsignedBigInteger('profile_id');
                $table->string('visibility_context', 40);
                $table->ipAddress('ip_address')->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamps();

                $table->index(['profile_type', 'profile_id', 'created_at'], 'idx_profile_view_logs_profile');
            });
        }

        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'feedback_type')) {
                $table->string('feedback_type', 20)->default('neutral')->after('rating');
            }
            if (! Schema::hasColumn('reviews', 'title')) {
                $table->string('title', 160)->nullable()->after('feedback_type');
            }
            if (! Schema::hasColumn('reviews', 'tags')) {
                $table->json('tags')->nullable()->after('comment');
            }
            if (! Schema::hasColumn('reviews', 'review_images')) {
                $table->json('review_images')->nullable()->after('tags');
            }
        });

        Schema::table('buyer_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('buyer_reviews', 'feedback_type')) {
                $table->string('feedback_type', 20)->default('neutral')->after('rating');
            }
            if (! Schema::hasColumn('buyer_reviews', 'title')) {
                $table->string('title', 160)->nullable()->after('feedback_type');
            }
            if (! Schema::hasColumn('buyer_reviews', 'tags')) {
                $table->json('tags')->nullable()->after('comment');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_view_logs');
        Schema::dropIfExists('trust_score_snapshots');
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_ratings');
        Schema::dropIfExists('marketplace_reviews');
        Schema::dropIfExists('buyer_profiles');
    }
};

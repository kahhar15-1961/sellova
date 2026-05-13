<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BuyerProfile;
use App\Models\BuyerReview;
use App\Models\Category;
use App\Models\MarketplaceReview;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProfileViewLog;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\SellerProfile;
use App\Models\Storefront;
use App\Models\User;
use App\Services\Marketplace\BuyerProfileService;
use App\Services\Marketplace\SellerProfileService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MarketplaceTrustProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('MarketplaceTrustProfileSeeder is disabled in production.');

            return;
        }

        DB::transaction(function (): void {
            $sellerUser = $this->user('trust-seller@example.test', 'Trust Seller');
            $buyerA = $this->user('trust-buyer-good@example.test', 'Reliable Buyer');
            $buyerB = $this->user('trust-buyer-risk@example.test', 'Cautious Buyer');
            $moderator = User::query()->where('email', 'admin@example.test')->first() ?? $this->user('trust-admin@example.test', 'Trust Admin');

            $seller = SellerProfile::query()->updateOrCreate(
                ['user_id' => (int) $sellerUser->id],
                [
                    'uuid' => (string) (SellerProfile::query()->where('user_id', (int) $sellerUser->id)->value('uuid') ?: Str::uuid()),
                    'display_name' => 'Trust Demo Store',
                    'legal_name' => 'Trust Demo Commerce LLC',
                    'country_code' => 'BD',
                    'default_currency' => 'BDT',
                    'verification_status' => 'verified',
                    'kyc_status' => 'approved',
                    'store_status' => 'active',
                    'store_logo_url' => 'https://placehold.co/320x320?text=Trust+Store',
                    'banner_image_url' => 'https://placehold.co/1280x420?text=Trusted+Marketplace+Store',
                    'city' => 'Dhaka',
                    'region' => 'Dhaka',
                    'country' => 'Bangladesh',
                    'processing_time_label' => '1-2 Business Days',
                    'store_policies_json' => [
                        ['label' => 'Delivery', 'value' => 'Ships verified orders within 1-2 business days.'],
                        ['label' => 'Escrow', 'value' => 'Accepts escrow-protected checkout for every marketplace order.'],
                        ['label' => 'Returns', 'value' => 'Return and refund requests are reviewed through Sellova support.'],
                    ],
                    'last_active_at' => now()->subMinutes(35),
                ],
            );

            Storefront::query()->updateOrCreate(
                ['seller_profile_id' => (int) $seller->id],
                [
                    'uuid' => (string) (Storefront::query()->where('seller_profile_id', (int) $seller->id)->value('uuid') ?: Str::uuid()),
                    'slug' => 'trust-demo-store',
                    'title' => 'Trust Demo Store',
                    'description' => 'Seeded store for testing seller details, ratings, policies, and trust scoring.',
                    'policy_text' => 'Escrow protected checkout, responsive support, and documented fulfillment.',
                    'is_public' => true,
                ],
            );

            $category = Category::query()->firstOrCreate(
                ['slug' => 'trust-demo'],
                [
                    'parent_id' => null,
                    'name' => 'Trust Demo',
                    'description' => 'Seed products for marketplace trust profile testing.',
                    'sort_order' => 900,
                    'is_active' => true,
                ],
            );

            $product = Product::query()->updateOrCreate(
                ['uuid' => '00000000-0000-0000-0000-00000000a101'],
                [
                    'seller_profile_id' => (int) $seller->id,
                    'storefront_id' => (int) Storefront::query()->where('seller_profile_id', (int) $seller->id)->value('id'),
                    'category_id' => (int) $category->id,
                    'product_type' => 'physical',
                    'title' => 'Trust Profile Test Product',
                    'description' => 'A seeded item used to test buyer and seller detail pages.',
                    'base_price' => '1250.0000',
                    'discount_percentage' => '0.0000',
                    'currency' => 'BDT',
                    'image_url' => 'https://placehold.co/960x720?text=Trust+Product',
                    'images_json' => ['https://placehold.co/960x720?text=Trust+Product'],
                    'attributes_json' => ['brand' => 'Sellova Trust', 'condition' => 'New'],
                    'status' => 'published',
                    'published_at' => now()->subDays(8),
                ],
            );

            $orders = [
                ['buyer' => $buyerA, 'number' => 'TRUST-SEED-001', 'rating' => 5, 'feedback' => 'good'],
                ['buyer' => $buyerA, 'number' => 'TRUST-SEED-002', 'rating' => 4, 'feedback' => 'good'],
                ['buyer' => $buyerB, 'number' => 'TRUST-SEED-003', 'rating' => 2, 'feedback' => 'bad'],
            ];

            foreach ($orders as $index => $spec) {
                /** @var User $buyer */
                $buyer = $spec['buyer'];
                $order = Order::query()->updateOrCreate(
                    ['order_number' => $spec['number']],
                    [
                        'uuid' => (string) (Order::query()->where('order_number', $spec['number'])->value('uuid') ?: Str::uuid()),
                        'buyer_user_id' => (int) $buyer->id,
                        'seller_user_id' => (int) $sellerUser->id,
                        'primary_product_id' => (int) $product->id,
                        'product_type' => 'physical',
                        'status' => 'completed',
                        'fulfillment_state' => 'completed',
                        'currency' => 'BDT',
                        'gross_amount' => '1250.0000',
                        'discount_amount' => '0.0000',
                        'fee_amount' => '60.0000',
                        'net_amount' => '1310.0000',
                        'placed_at' => now()->subDays(12 - $index),
                        'completed_at' => now()->subDays(9 - $index),
                    ],
                );

                $item = OrderItem::query()->updateOrCreate(
                    ['order_id' => (int) $order->id, 'product_id' => (int) $product->id],
                    [
                        'uuid' => (string) (OrderItem::query()->where('order_id', (int) $order->id)->where('product_id', (int) $product->id)->value('uuid') ?: Str::uuid()),
                        'seller_profile_id' => (int) $seller->id,
                        'product_variant_id' => null,
                        'product_type_snapshot' => 'physical',
                        'title_snapshot' => 'Trust Profile Test Product',
                        'sku_snapshot' => 'TRUST-PROFILE',
                        'quantity' => 1,
                        'unit_price_snapshot' => '1250.0000',
                        'line_total_snapshot' => '1250.0000',
                        'commission_rule_snapshot_json' => [],
                        'delivery_state' => 'delivered',
                    ],
                );

                $this->sellerReview($buyer, $seller, $order, $item, (int) $spec['rating'], (string) $spec['feedback']);
                $this->buyerReview($sellerUser, $seller, $buyer, $order, $index === 2 ? 2 : 5, $index === 2 ? 'bad' : 'good');
            }

            $reportedReview = MarketplaceReview::query()
                ->where('reviewed_role', 'seller')
                ->where('reviewed_id', (int) $seller->id)
                ->where('feedback_type', 'bad')
                ->first();
            if ($reportedReview instanceof MarketplaceReview) {
                ReviewReport::query()->updateOrCreate(
                    ['marketplace_review_id' => (int) $reportedReview->id, 'reporter_id' => (int) $sellerUser->id],
                    [
                        'reason_code' => 'unfair_or_inaccurate',
                        'details' => 'Seeded report for admin moderation testing.',
                        'status' => 'open',
                        'reviewed_by' => (int) $moderator->id,
                        'reviewed_at' => null,
                    ],
                );
            }

            app(SellerProfileService::class)->details($seller, true);
            app(BuyerProfileService::class)->details($buyerA, $sellerUser, true);
            app(BuyerProfileService::class)->details($buyerB, $sellerUser, true);

            ProfileViewLog::query()->firstOrCreate([
                'viewer_id' => (int) $buyerA->id,
                'profile_type' => 'seller',
                'profile_id' => (int) $seller->id,
                'visibility_context' => 'seed',
            ], [
                'viewer_role' => 'buyer',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'MarketplaceTrustProfileSeeder',
            ]);
        });

        $this->command?->info('Marketplace trust profile demo data seeded.');
        $this->command?->line('  Seller profile: /profiles/sellers/'.SellerProfile::query()->where('display_name', 'Trust Demo Store')->value('id'));
        $this->command?->line('  Buyer profile: /profiles/buyers/'.User::query()->where('email', 'trust-buyer-good@example.test')->value('id'));
    }

    private function user(string $email, string $name): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'uuid' => (string) (User::query()->where('email', $email)->value('uuid') ?: Str::uuid()),
                'display_name' => $name,
                'phone' => null,
                'password_hash' => password_hash(LocalAppSeeder::PASSWORD_PLAIN, PASSWORD_DEFAULT),
                'status' => 'active',
                'risk_level' => str_contains($email, 'risk') ? 'medium' : 'low',
                'restricted_checkout' => false,
                'last_login_at' => now()->subHours(str_contains($email, 'risk') ? 18 : 2),
            ],
        );
    }

    private function sellerReview(User $buyer, SellerProfile $seller, Order $order, OrderItem $item, int $rating, string $feedback): void
    {
        Review::query()->updateOrCreate(
            ['order_item_id' => (int) $item->id],
            [
                'uuid' => (string) (Review::query()->where('order_item_id', (int) $item->id)->value('uuid') ?: Str::uuid()),
                'buyer_user_id' => (int) $buyer->id,
                'seller_profile_id' => (int) $seller->id,
                'product_id' => (int) $item->product_id,
                'rating' => $rating,
                'feedback_type' => $feedback,
                'title' => $feedback === 'bad' ? 'Delivery needed more clarity' : 'Smooth verified purchase',
                'comment' => $feedback === 'bad'
                    ? 'The product arrived, but tracking and response quality could be improved.'
                    : 'Professional seller, clear updates, and the product matched the listing.',
                'tags' => $feedback === 'bad' ? ['slow_response', 'tracking_needed'] : ['fast_delivery', 'accurate_listing', 'good_support'],
                'review_images' => [],
                'status' => 'visible',
                'helpful_count' => $feedback === 'bad' ? 1 : 6,
            ],
        );

        $review = MarketplaceReview::query()->updateOrCreate(
            [
                'reviewer_id' => (int) $buyer->id,
                'reviewed_role' => 'seller',
                'reviewed_id' => (int) $seller->id,
                'order_id' => (int) $order->id,
            ],
            [
                'uuid' => (string) (MarketplaceReview::query()
                    ->where('reviewer_id', (int) $buyer->id)
                    ->where('reviewed_role', 'seller')
                    ->where('reviewed_id', (int) $seller->id)
                    ->where('order_id', (int) $order->id)
                    ->value('uuid') ?: Str::uuid()),
                'reviewer_role' => 'buyer',
                'rating' => $rating,
                'feedback_type' => $feedback,
                'title' => $feedback === 'bad' ? 'Delivery needed more clarity' : 'Smooth verified purchase',
                'comment' => $feedback === 'bad'
                    ? 'The product arrived, but tracking and response quality could be improved.'
                    : 'Professional seller, clear updates, and the product matched the listing.',
                'tags' => $feedback === 'bad' ? ['slow_response', 'tracking_needed'] : ['fast_delivery', 'accurate_listing', 'good_support'],
                'review_images' => [],
                'is_verified_order' => true,
                'status' => 'visible',
            ],
        );

        $this->ratings($review, [
            'product_quality' => $feedback === 'bad' ? 3 : 5,
            'delivery_speed' => $feedback === 'bad' ? 2 : 5,
            'communication' => $feedback === 'bad' ? 2 : 5,
            'accuracy' => $feedback === 'bad' ? 4 : 5,
            'support_behavior' => $feedback === 'bad' ? 3 : 5,
        ]);
    }

    private function buyerReview(User $sellerUser, SellerProfile $seller, User $buyer, Order $order, int $rating, string $feedback): void
    {
        BuyerProfile::query()->updateOrCreate(
            ['user_id' => (int) $buyer->id],
            [
                'display_name' => (string) ($buyer->display_name ?? 'Buyer #'.$buyer->id),
                'avatar_url' => 'https://placehold.co/320x320?text='.rawurlencode((string) ($buyer->display_name ?? 'Buyer')),
                'verification_status' => $feedback === 'bad' ? 'unverified' : 'verified',
                'kyc_status' => $feedback === 'bad' ? 'not_submitted' : 'approved',
                'communication_rating' => $feedback === 'bad' ? 2 : 5,
                'payment_reliability_rating' => $feedback === 'bad' ? 2 : 5,
                'cooperation_rating' => $feedback === 'bad' ? 2 : 5,
                'public_badges_json' => $feedback === 'bad' ? ['Needs clearer communication'] : ['Fast payer', 'Verified order history'],
                'last_active_at' => $buyer->last_login_at,
            ],
        );

        BuyerReview::query()->updateOrCreate(
            ['order_id' => (int) $order->id, 'seller_profile_id' => (int) $seller->id],
            [
                'uuid' => (string) (BuyerReview::query()->where('order_id', (int) $order->id)->where('seller_profile_id', (int) $seller->id)->value('uuid') ?: Str::uuid()),
                'seller_user_id' => (int) $sellerUser->id,
                'buyer_user_id' => (int) $buyer->id,
                'rating' => $rating,
                'feedback_type' => $feedback,
                'title' => $feedback === 'bad' ? 'Hard to coordinate' : 'Reliable buyer',
                'comment' => $feedback === 'bad'
                    ? 'Payment completed, but communication and confirmation took extra follow-up.'
                    : 'Buyer paid promptly, communicated clearly, and confirmed completion quickly.',
                'tags' => $feedback === 'bad' ? ['slow_reply', 'needed_follow_up'] : ['fast_payment', 'clear_communication'],
                'status' => 'visible',
            ],
        );

        $review = MarketplaceReview::query()->updateOrCreate(
            [
                'reviewer_id' => (int) $sellerUser->id,
                'reviewed_role' => 'buyer',
                'reviewed_id' => (int) $buyer->id,
                'order_id' => (int) $order->id,
            ],
            [
                'uuid' => (string) (MarketplaceReview::query()
                    ->where('reviewer_id', (int) $sellerUser->id)
                    ->where('reviewed_role', 'buyer')
                    ->where('reviewed_id', (int) $buyer->id)
                    ->where('order_id', (int) $order->id)
                    ->value('uuid') ?: Str::uuid()),
                'reviewer_role' => 'seller',
                'rating' => $rating,
                'feedback_type' => $feedback,
                'title' => $feedback === 'bad' ? 'Hard to coordinate' : 'Reliable buyer',
                'comment' => $feedback === 'bad'
                    ? 'Payment completed, but communication and confirmation took extra follow-up.'
                    : 'Buyer paid promptly, communicated clearly, and confirmed completion quickly.',
                'tags' => $feedback === 'bad' ? ['slow_reply', 'needed_follow_up'] : ['fast_payment', 'clear_communication'],
                'review_images' => [],
                'is_verified_order' => true,
                'status' => 'visible',
            ],
        );

        $this->ratings($review, [
            'payment_reliability' => $feedback === 'bad' ? 2 : 5,
            'communication' => $feedback === 'bad' ? 2 : 5,
            'cooperation' => $feedback === 'bad' ? 2 : 5,
            'dispute_behavior' => $feedback === 'bad' ? 3 : 5,
            'order_completion_behavior' => $feedback === 'bad' ? 3 : 5,
        ]);
    }

    /**
     * @param  array<string, int>  $ratings
     */
    private function ratings(MarketplaceReview $review, array $ratings): void
    {
        $review->ratings()->delete();
        foreach ($ratings as $category => $rating) {
            $review->ratings()->create([
                'category' => $category,
                'rating' => $rating,
            ]);
        }
    }
}

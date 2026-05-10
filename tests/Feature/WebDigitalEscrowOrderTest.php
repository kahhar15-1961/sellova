<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Enums\EscrowState;
use App\Domain\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DigitalDelivery;
use App\Models\DisputeCase;
use App\Models\EscrowAccount;
use App\Models\InventoryRecord;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StaffUser;
use App\Models\Storefront;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WebDigitalEscrowOrderTest extends TestCase
{
    public function test_seller_can_submit_digital_delivery_and_buyer_can_release_escrow_with_secure_file_download(): void
    {
        [$buyer, $sellerUser] = $this->seedDigitalCheckoutOrder();
        $order = Order::query()->firstOrFail();

        $sellerDelivery = $this->actingAs($sellerUser)
            ->withSession(['_token' => 'digital-delivery-token'])
            ->withHeader('X-CSRF-TOKEN', 'digital-delivery-token')
            ->post('/web/actions/orders/'.$order->id.'/escrow/delivery', [
                'delivery_message' => 'Files are ready for review.',
                'delivery_version' => 'v1',
                'files' => [UploadedFile::fake()->create('deliverable.zip', 120, 'application/zip')],
            ]);

        $sellerDelivery->assertOk();

        $order->refresh();
        self::assertSame(OrderStatus::BuyerReview, $order->status);
        self::assertSame('delivered', (string) $order->delivery_status);
        self::assertSame(1, (int) $order->delivery_files_count);
        self::assertSame(1, DigitalDelivery::query()->where('order_id', $order->id)->count());

        $detailResponse = $this->actingAs($buyer)->getJson('/web/actions/orders/'.$order->id.'/escrow');
        $detailResponse->assertOk()->assertJsonPath('detail.available_actions.release_funds', true);

        $downloadUrl = (string) $detailResponse->json('detail.delivery.files.0.download_url');
        $this->actingAs($buyer)->get($downloadUrl)->assertOk();

        $releaseResponse = $this->actingAs($buyer)
            ->withSession(['_token' => 'digital-release-token'])
            ->withHeader('X-CSRF-TOKEN', 'digital-release-token')
            ->postJson('/web/actions/orders/'.$order->id.'/escrow/release');

        $releaseResponse->assertOk();

        $order->refresh();
        $escrow = EscrowAccount::query()->where('order_id', $order->id)->firstOrFail();
        self::assertSame(OrderStatus::Completed, $order->status);
        self::assertSame('released', (string) $order->escrow_status);
        self::assertSame(EscrowState::Released, $escrow->state);
    }

    public function test_buyer_can_open_dispute_for_digital_escrow_order_before_release(): void
    {
        [$buyer, $sellerUser] = $this->seedDigitalCheckoutOrder();
        $order = Order::query()->firstOrFail();

        $this->actingAs($sellerUser)
            ->withSession(['_token' => 'digital-dispute-delivery-token'])
            ->withHeader('X-CSRF-TOKEN', 'digital-dispute-delivery-token')
            ->post('/web/actions/orders/'.$order->id.'/escrow/delivery', [
                'delivery_message' => 'Initial delivery shared.',
                'delivery_version' => 'v1',
            ])
            ->assertOk();

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'digital-dispute-token'])
            ->withHeader('X-CSRF-TOKEN', 'digital-dispute-token')
            ->postJson('/web/actions/orders/'.$order->id.'/escrow/dispute', [
                'reason_code' => 'delivery_issue',
            ]);

        $response->assertOk();

        $order->refresh();
        self::assertSame(OrderStatus::Disputed, $order->status);
        self::assertSame('disputed', (string) $order->escrow_status);
        self::assertSame(1, DisputeCase::query()->where('order_id', $order->id)->count());
    }

    /**
     * @return array{0: StaffUser, 1: StaffUser}
     */
    private function seedDigitalCheckoutOrder(): array
    {
        $buyer = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'buyer-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $sellerUser = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Digital Seller',
            'country_code' => 'BD',
            'default_currency' => 'BDT',
            'verification_status' => 'verified',
            'store_status' => 'active',
        ]);
        $storefront = Storefront::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'slug' => 'digital-'.Str::random(8),
            'title' => 'Digital Store',
            'is_public' => true,
        ]);
        $category = Category::query()->create([
            'slug' => 'digital-'.Str::random(8),
            'name' => 'Digital',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'storefront_id' => $storefront->id,
            'category_id' => $category->id,
            'product_type' => 'digital',
            'title' => 'Escrow Design Package',
            'description' => 'Digital delivery product',
            'base_price' => '25.0000',
            'discount_percentage' => '0.00',
            'currency' => 'BDT',
            'status' => 'published',
            'published_at' => now(),
        ]);
        InventoryRecord::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
            'stock_sold' => 0,
            'version' => 1,
        ]);
        $cart = Cart::query()->create([
            'uuid' => (string) Str::uuid(),
            'buyer_user_id' => $buyer->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'seller_profile_id' => $seller->id,
            'quantity' => 1,
            'unit_price_snapshot' => '25.0000',
            'currency_snapshot' => 'BDT',
            'metadata_snapshot_json' => ['title' => 'Escrow Design Package'],
        ]);

        $this->actingAs($buyer)
            ->withSession(['_token' => 'digital-checkout-token'])
            ->withHeader('X-CSRF-TOKEN', 'digital-checkout-token')
            ->postJson('/web/actions/checkout', [
                'shipping_address_line' => '12 Market Road',
                'shipping_method' => 'standard',
                'payment_method' => 'wallet',
            ])
            ->assertOk();

        return [$buyer, $sellerUser];
    }
}

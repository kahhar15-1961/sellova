<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Enums\EscrowState;
use App\Domain\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\EscrowAccount;
use App\Models\InventoryRecord;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StaffUser;
use App\Models\Storefront;
use App\Models\UserAddress;
use App\Models\UserPaymentMethod;
use App\Models\User;
use App\Services\Order\EscrowOrderDetailService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WebBuyerCheckoutTest extends TestCase
{
    public function test_signed_in_buyer_can_checkout_with_wallet_and_immediately_sees_funded_escrow(): void
    {
        [$buyer, $product] = $this->seedBuyerCart();

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'checkout-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-test-token')
            ->postJson('/web/actions/checkout', [
                'shipping_address_line' => '12 Market Road',
                'shipping_method' => 'standard',
                'payment_method' => 'wallet',
            ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $order = Order::query()->firstOrFail();
        self::assertSame(OrderStatus::EscrowFunded, $order->status);
        self::assertSame($product->id, (int) $order->primary_product_id);

        $escrow = EscrowAccount::query()->where('order_id', $order->id)->firstOrFail();
        self::assertSame(EscrowState::Held, $escrow->state);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'status' => 'success',
            'amount' => (string) $order->net_amount,
        ]);
        $this->assertDatabaseHas('carts', [
            'buyer_user_id' => $buyer->id,
            'status' => 'checked_out',
        ]);
        self::assertSame(0, CartItem::query()->count());

        $orders = $response->json('marketplace.orders');
        self::assertSame($order->order_number, $orders[0]['id']);
        self::assertSame('escrow_funded', $orders[0]['status']);
        self::assertSame('held', $orders[0]['escrow_state']);
        self::assertSame('wallet', $orders[0]['payment_method']);
        self::assertSame('Escrow funded', $orders[0]['stage']);
    }

    public function test_signed_in_buyer_can_checkout_with_manual_payment_and_immediately_sees_funded_escrow(): void
    {
        [$buyer] = $this->seedBuyerCart();

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'checkout-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-test-token')
            ->postJson('/web/actions/checkout', [
                'shipping_address_line' => '12 Market Road',
                'shipping_method' => 'standard',
                'payment_method' => 'manual',
                'payment_reference' => 'BANK-REF-123',
            ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $order = Order::query()->firstOrFail();
        self::assertSame(OrderStatus::EscrowFunded, $order->status);
        self::assertSame(EscrowState::Held, EscrowAccount::query()->where('order_id', $order->id)->firstOrFail()->state);

        $transaction = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
        self::assertSame('bank', $transaction->raw_payload_json['method']);
        self::assertSame('BANK-REF-123', $transaction->raw_payload_json['provider_reference']);
        self::assertSame('held', $response->json('marketplace.orders.0.escrow_state'));
        self::assertSame('bank', $response->json('marketplace.orders.0.payment_method'));
    }

    public function test_checkout_can_use_saved_address_and_saved_payment_method_context(): void
    {
        [$buyer] = $this->seedBuyerCart();

        $address = UserAddress::query()->create([
            'user_id' => $buyer->id,
            'label' => 'HQ',
            'address_type' => 'shipping',
            'recipient_name' => 'Buyer Checkout',
            'phone' => '01700000000',
            'address_line' => '88 Commerce Avenue',
            'city' => 'Dhaka',
            'region' => 'Dhaka',
            'postal_code' => '1207',
            'country' => 'Bangladesh',
            'is_default' => true,
        ]);

        $paymentMethod = UserPaymentMethod::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $buyer->id,
            'kind' => 'bank',
            'label' => 'Primary Bank',
            'subtitle' => 'A/C 1245',
            'details_json' => ['bank_name' => 'Test Bank'],
            'is_default' => true,
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'checkout-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-test-token')
            ->postJson('/web/actions/checkout', [
                'address_id' => $address->id,
                'shipping_method' => 'express',
                'payment_method' => 'manual',
                'payment_method_id' => $paymentMethod->id,
            ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $order = Order::query()->firstOrFail();
        self::assertSame('88 Commerce Avenue', $order->shipping_address_line);
        self::assertSame('Buyer Checkout', $order->shipping_recipient_name);
        self::assertSame('01700000000', $order->shipping_phone);
        self::assertSame('express', $order->shipping_method);

        $transaction = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
        self::assertStringContainsString((string) $paymentMethod->id, (string) ($transaction->raw_payload_json['provider_reference'] ?? ''));
    }

    public function test_physical_checkout_requires_shipping_address_context(): void
    {
        [$buyer] = $this->seedBuyerCart();

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'checkout-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-test-token')
            ->postJson('/web/actions/checkout', [
                'shipping_method' => 'standard',
                'payment_method' => 'wallet',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'A shipping address is required before placing this order.');
    }

    public function test_digital_checkout_does_not_require_shipping_address(): void
    {
        [$buyer, $product] = $this->seedBuyerCart(productType: 'digital');
        $product->attributes_json = [
            'is_instant_delivery' => true,
            'delivery_fulfillment_hours' => 6,
        ];
        $product->save();

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'checkout-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-test-token')
            ->postJson('/web/actions/checkout', [
                'payment_method' => 'wallet',
            ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $order = Order::query()->firstOrFail();
        self::assertNull($order->shipping_address_line);
        self::assertSame(OrderStatus::EscrowFunded, $order->status);
        self::assertSame('pending', $order->delivery_status);
        self::assertNotNull($order->seller_deadline_at);
        self::assertEqualsWithDelta(6 * 3600, now()->diffInSeconds($order->seller_deadline_at, false), 10);

        $detail = app(EscrowOrderDetailService::class)->build($order->fresh('orderItems'), (int) $buyer->id);
        self::assertSame('digital_escrow', $detail['flow_type']);
        self::assertSame('escrow_held', $detail['order']['ui_status']);
        self::assertSame('pending', $detail['order']['delivery_status']);
        self::assertSame('pending', collect($detail['timeline'])->firstWhere('key', 'delivered')['state'] ?? null);
    }

    public function test_service_checkout_does_not_require_shipping_address(): void
    {
        [$buyer] = $this->seedBuyerCart(productType: 'service');

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'checkout-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-test-token')
            ->postJson('/web/actions/checkout', [
                'payment_method' => 'wallet',
            ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $order = Order::query()->firstOrFail();
        self::assertNull($order->shipping_address_line);
        self::assertSame('service', (string) $order->product_type);
        self::assertSame(OrderStatus::EscrowFunded, $order->status);
    }

    public function test_seller_product_digital_flags_resolve_fulfillment_type_and_delivery_time(): void
    {
        $sellerUser = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Flag Seller',
            'country_code' => 'BD',
            'default_currency' => 'BDT',
            'verification_status' => 'verified',
            'store_status' => 'active',
        ]);
        $category = Category::query()->create([
            'slug' => 'flags-'.Str::random(8),
            'name' => 'Flag Products',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($sellerUser)
            ->withSession(['_token' => 'seller-product-token'])
            ->withHeader('X-CSRF-TOKEN', 'seller-product-token')
            ->postJson('/web/actions/seller/products', [
                'title' => 'Managed Service Delivery',
                'category_id' => $category->id,
                'product_type' => 'digital',
                'price' => '120',
                'stock' => 1,
                'status' => 'draft',
                'is_service_product' => true,
                'is_instant_delivery' => false,
                'delivery_fulfillment_hours' => 36,
            ])
            ->assertOk();

        $serviceProduct = Product::query()->where('seller_profile_id', $seller->id)->firstOrFail();
        self::assertSame('service', $serviceProduct->product_type);
        self::assertTrue((bool) ($serviceProduct->attributes_json['is_service_product'] ?? false));
        self::assertFalse((bool) ($serviceProduct->attributes_json['is_instant_delivery'] ?? true));
        self::assertSame(36, (int) $serviceProduct->attributes_json['delivery_fulfillment_hours']);

        $this->actingAs($sellerUser)
            ->withSession(['_token' => 'seller-product-token-2'])
            ->withHeader('X-CSRF-TOKEN', 'seller-product-token-2')
            ->postJson('/web/actions/seller/products', [
                'title' => 'Digital Shell Without Digital Flow',
                'category_id' => $category->id,
                'product_type' => 'digital',
                'price' => '80',
                'stock' => 1,
                'status' => 'draft',
                'is_service_product' => false,
                'is_instant_delivery' => false,
                'delivery_fulfillment_hours' => 24,
            ])
            ->assertOk();

        $physicalProduct = Product::query()->where('title', 'Digital Shell Without Digital Flow')->firstOrFail();
        self::assertSame('physical', $physicalProduct->product_type);
    }

    public function test_checkout_splits_multi_seller_cart_into_separate_protected_orders(): void
    {
        [$buyer, $firstProduct] = $this->seedBuyerCart();
        $secondProduct = $this->addCartProductForNewSeller((int) $buyer->id);

        $response = $this->actingAs($buyer)
            ->withSession(['_token' => 'checkout-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-test-token')
            ->postJson('/web/actions/checkout', [
                'shipping_address_line' => '12 Market Road',
                'shipping_method' => 'standard',
                'payment_method' => 'wallet',
            ]);

        $response->assertOk()->assertJsonPath('ok', true);

        self::assertSame(2, Order::query()->count());
        self::assertCount(2, $response->json('order_ids'));
        self::assertStringStartsWith('/buyer/orders/', (string) $response->json('redirect_url'));

        $orderedProductIds = Order::query()
            ->with('orderItems')
            ->get()
            ->flatMap(static fn (Order $order) => $order->orderItems->pluck('product_id'))
            ->map(static fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        self::assertSame([(int) $firstProduct->id, (int) $secondProduct->id], $orderedProductIds);
        self::assertSame(2, EscrowAccount::query()->count());
        self::assertSame(0, CartItem::query()->count());
    }

    public function test_checkout_page_redirects_guest_to_login_and_preserves_intended_checkout(): void
    {
        $response = $this->get('/checkout');

        $response->assertRedirect('/login');
        self::assertSame('/checkout', session('url.intended'));
        self::assertSame('buyer', session('auth.panel'));
    }

    public function test_guest_cart_merges_into_buyer_cart_after_login_and_returns_to_checkout(): void
    {
        [, $product] = $this->seedBuyerCart(productType: 'digital');
        $buyer = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'returning-'.Str::random(8).'@example.test',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $response = $this
            ->withSession([
                '_token' => 'login-checkout-token',
                'url.intended' => '/checkout',
                'auth.panel' => 'buyer',
                'web_cart' => [$product->id => 1],
                'web_cart_snapshots' => [
                    $product->id => [
                        'title' => $product->title,
                        'price' => 25,
                        'currency' => 'BDT',
                    ],
                ],
            ])
            ->withHeader('X-CSRF-TOKEN', 'login-checkout-token')
            ->post('/login', [
                'email' => $buyer->email,
                'password' => 'password123',
                'panel' => 'buyer',
            ]);

        $response->assertRedirect('/checkout');
        $this->assertAuthenticatedAs($buyer);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        self::assertNull(session('web_cart'));
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function seedBuyerCart(string $productType = 'physical'): array
    {
        $buyer = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'buyer-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $sellerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Checkout Seller',
            'country_code' => 'BD',
            'default_currency' => 'BDT',
            'verification_status' => 'verified',
            'store_status' => 'active',
        ]);
        $storefront = Storefront::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'slug' => 'checkout-'.Str::random(8),
            'title' => 'Checkout Store',
            'is_public' => true,
        ]);
        $category = Category::query()->create([
            'slug' => 'checkout-'.Str::random(8),
            'name' => 'Checkout',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'storefront_id' => $storefront->id,
            'category_id' => $category->id,
            'product_type' => $productType,
            'title' => 'Escrow Test Item',
            'description' => 'A real checkout test item.',
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
            'metadata_snapshot_json' => ['title' => 'Escrow Test Item'],
        ]);

        return [$buyer, $product];
    }

    private function addCartProductForNewSeller(int $buyerId, string $productType = 'physical'): Product
    {
        $sellerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-'.Str::random(8).'@example.test',
            'password_hash' => 'hash',
        ]);
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Second Checkout Seller',
            'country_code' => 'BD',
            'default_currency' => 'BDT',
            'verification_status' => 'verified',
            'store_status' => 'active',
        ]);
        $storefront = Storefront::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'slug' => 'checkout-'.Str::random(8),
            'title' => 'Second Checkout Store',
            'is_public' => true,
        ]);
        $category = Category::query()->create([
            'slug' => 'checkout-'.Str::random(8),
            'name' => 'Checkout',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'storefront_id' => $storefront->id,
            'category_id' => $category->id,
            'product_type' => $productType,
            'title' => 'Second Escrow Test Item',
            'description' => 'A second checkout test item.',
            'base_price' => '30.0000',
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

        $cart = Cart::query()
            ->where('buyer_user_id', $buyerId)
            ->where('status', 'active')
            ->firstOrFail();

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'seller_profile_id' => $seller->id,
            'quantity' => 1,
            'unit_price_snapshot' => '30.0000',
            'currency_snapshot' => 'BDT',
            'metadata_snapshot_json' => ['title' => 'Second Escrow Test Item'],
        ]);

        return $product;
    }
}

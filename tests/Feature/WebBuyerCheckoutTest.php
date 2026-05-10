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
use App\Models\User;
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

    /**
     * @return array{0: User, 1: Product}
     */
    private function seedBuyerCart(): array
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
            'product_type' => 'physical',
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
}

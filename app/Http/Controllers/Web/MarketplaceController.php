<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Commands\Order\CreateOrderCommand;
use App\Domain\Commands\Product\CreateProductCommand;
use App\Domain\Value\CartLineItem;
use App\Domain\Value\CartSnapshot;
use App\Domain\Value\ProductDraft;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\InventoryRecord;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SellerProfile;
use App\Models\UserWishlistItem;
use App\Models\WithdrawalRequest;
use App\Services\Order\OrderService;
use App\Services\Product\ProductService;
use App\Services\Promotion\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class MarketplaceController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly OrderService $orderService,
        private readonly PromotionService $promotionService = new PromotionService(),
    ) {
    }

    public function home(): Response
    {
        return $this->render('buyer', 'home');
    }

    public function buyer(): Response
    {
        return $this->render('buyer', 'home');
    }

    public function seller(): Response
    {
        return $this->render('seller', 'seller-dashboard');
    }

    public function marketplace(): Response
    {
        return $this->render('buyer', 'marketplace');
    }

    public function product(int $productId): Response
    {
        return $this->render('buyer', 'product', $productId);
    }

    public function buyerView(string $view): Response
    {
        return $this->render('buyer', $view);
    }

    public function sellerView(?string $view = null): Response
    {
        return $this->render('seller', $view === null ? 'seller-dashboard' : 'seller-'.$view);
    }

    public function cartAdd(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'product_snapshot' => ['nullable', 'array'],
        ]);
        $productId = (int) $payload['product_id'];
        $product = Product::query()->whereKey($productId)->first();
        $quantity = (int) ($payload['quantity'] ?? 1);

        if (Auth::check()) {
            if (! $product instanceof Product) {
                return response()->json(['ok' => false, 'message' => 'Product not found.'], 404);
            }

            $cart = $this->activeCart();
            $item = CartItem::query()->firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
            ]);
            $item->fill([
                'seller_profile_id' => (int) $product->seller_profile_id,
                'quantity' => max(1, (int) ($item->exists ? $item->quantity : 0) + $quantity),
                'unit_price_snapshot' => (string) $product->base_price,
                'currency_snapshot' => (string) ($product->currency ?? 'BDT'),
                'metadata_snapshot_json' => [
                    'title' => $product->title,
                    'image_url' => $product->image_url,
                ],
            ])->save();
        } else {
            $cart = $request->session()->get('web_cart', []);
            $cart[$productId] = max(1, (int) ($cart[$productId] ?? 0) + $quantity);
            $request->session()->put('web_cart', $cart);

            $snapshots = $request->session()->get('web_cart_snapshots', []);
            $snapshot = $product instanceof Product
                ? $this->productPayload($product)
                : $this->sanitizeProductSnapshot($payload['product_snapshot'] ?? [], $productId);
            if ($snapshot !== []) {
                $snapshots[$productId] = $snapshot;
                $request->session()->put('web_cart_snapshots', $snapshots);
            }
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function cartUpdate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);
        $productId = (int) $payload['product_id'];
        $quantity = (int) $payload['quantity'];

        if (Auth::check()) {
            $cart = $this->activeCart();
            $query = CartItem::query()->where('cart_id', $cart->id)->where('product_id', $productId);
            $quantity <= 0 ? $query->delete() : $query->update(['quantity' => $quantity]);
        } else {
            $cart = $request->session()->get('web_cart', []);
            if ($quantity <= 0) {
                unset($cart[$productId]);
                $snapshots = $request->session()->get('web_cart_snapshots', []);
                unset($snapshots[$productId]);
                $request->session()->put('web_cart_snapshots', $snapshots);
            } else {
                $cart[$productId] = $quantity;
            }
            $request->session()->put('web_cart', $cart);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'shipping_address_line' => ['nullable', 'string', 'max:5000'],
            'shipping_method' => ['nullable', 'string', 'in:standard,express'],
            'payment_method' => ['nullable', 'string', 'max:64'],
        ]);

        if (Auth::check()) {
            $cart = $this->activeCart();
            $cart->load('cartItems');
            if ($cart->cartItems->isEmpty()) {
                return response()->json(['ok' => false, 'message' => 'Cart is empty.'], 422);
            }
            $lines = $cart->cartItems->map(static fn (CartItem $item): CartLineItem => new CartLineItem(
                productId: (int) $item->product_id,
                productVariantId: $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                sellerProfileId: (int) $item->seller_profile_id,
                quantity: (int) $item->quantity,
                unitPrice: (string) $item->unit_price_snapshot,
                currency: (string) $item->currency_snapshot,
            ))->values()->all();
            $this->orderService->createOrder(new CreateOrderCommand(
                buyerUserId: (int) Auth::id(),
                cartSnapshot: new CartSnapshot($lines),
                idempotencyKey: 'web-checkout-'.Auth::id().'-'.Str::uuid(),
                shippingMethod: (string) ($payload['shipping_method'] ?? 'standard'),
                shippingMethodProvided: true,
                shippingAddressLine: $payload['shipping_address_line'] ?? null,
            ));
            $cart->cartItems()->delete();
            $cart->status = 'checked_out';
            $cart->save();
        } else {
            $orders = $request->session()->get('web_guest_orders', []);
            $cart = $request->session()->get('web_cart', []);
            if ($cart === []) {
                return response()->json(['ok' => false, 'message' => 'Cart is empty.'], 422);
            }
            $amount = collect($this->cartPayload())->sum(static fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1));
            $orders[] = [
                'id' => 'GUEST-'.now()->format('His'),
                'cart' => $cart,
                'amount' => $amount,
                'items_count' => count($cart),
                'status' => 'Pending account sign in',
                'shipping_address_line' => $payload['shipping_address_line'] ?? null,
                'created_at' => now()->toIso8601String(),
            ];
            $request->session()->put('web_guest_orders', $orders);
            $request->session()->forget('web_cart');
            $request->session()->forget('web_cart_snapshots');
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function wishlistToggle(Request $request): JsonResponse
    {
        $payload = $request->validate(['product_id' => ['required', 'integer', 'min:1']]);
        $productId = (int) $payload['product_id'];
        if (Auth::check()) {
            $existing = UserWishlistItem::query()->where('user_id', Auth::id())->where('product_id', $productId)->first();
            $existing === null
                ? UserWishlistItem::query()->create(['user_id' => Auth::id(), 'product_id' => $productId])
                : $existing->delete();
        } else {
            $wishlist = $request->session()->get('web_wishlist', []);
            in_array($productId, $wishlist, true)
                ? $wishlist = array_values(array_diff($wishlist, [$productId]))
                : $wishlist[] = $productId;
            $request->session()->put('web_wishlist', array_values(array_unique($wishlist)));
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerProductStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:191'],
            'type' => ['nullable', 'string', 'max:64'],
            'condition' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (Auth::check() && Auth::user()?->sellerProfile !== null) {
            $seller = Auth::user()->sellerProfile;
            $categoryId = (int) ($payload['category_id'] ?? $this->fallbackCategoryId());
            $this->productService->createProduct(new CreateProductCommand(
                sellerProfileId: (int) $seller->id,
                draft: new ProductDraft(
                    storefrontId: (int) ($seller->storefront?->id ?? 0),
                    categoryId: $categoryId,
                    productType: strtolower((string) ($payload['type'] ?? 'physical')) === 'classified' ? 'classified' : 'physical',
                    title: (string) $payload['title'],
                    description: $payload['description'] ?? null,
                    basePrice: (string) $payload['price'],
                    currency: (string) ($seller->default_currency ?? 'BDT'),
                    stock: (int) ($payload['stock'] ?? 1),
                    status: 'published',
                    imageUrl: $payload['image_url'] ?? null,
                    imageUrls: array_values(array_filter([(string) ($payload['image_url'] ?? '')])),
                    attributes: [
                        'condition' => $payload['condition'] ?? 'New',
                        'tags' => ['web', 'seller'],
                    ],
                ),
            ));
        } else {
            $drafts = $request->session()->get('web_seller_products', []);
            array_unshift($drafts, $payload + ['id' => 'draft-'.Str::uuid(), 'created_at' => now()->toIso8601String()]);
            $request->session()->put('web_seller_products', $drafts);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function inventoryAdjust(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'delta' => ['required', 'integer', 'min:-9999', 'max:9999'],
        ]);

        $productId = (int) $payload['product_id'];
        $delta = (int) $payload['delta'];

        if (Auth::check() && Auth::user()?->sellerProfile !== null) {
            $product = Product::query()
                ->where('seller_profile_id', Auth::user()->sellerProfile->id)
                ->whereKey($productId)
                ->first();

            if ($product instanceof Product) {
                $record = InventoryRecord::query()->firstOrCreate(
                    ['product_id' => $product->id, 'product_variant_id' => null],
                    ['stock_on_hand' => 0, 'stock_reserved' => 0, 'stock_sold' => 0, 'version' => 1]
                );
                $record->stock_on_hand = max(0, (int) $record->stock_on_hand + $delta);
                $record->version = (int) $record->version + 1;
                $record->save();
            }
        } else {
            $adjustments = $request->session()->get('web_inventory_adjustments', []);
            $adjustments[$productId] = (int) ($adjustments[$productId] ?? 0) + $delta;
            $request->session()->put('web_inventory_adjustments', $adjustments);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function couponStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'value' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (Auth::check() && Auth::user()?->sellerProfile !== null) {
            Promotion::query()->updateOrCreate(
                ['code' => Str::upper((string) $payload['code'])],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => Str::title(str_replace(['-', '_'], ' ', (string) $payload['code'])),
                    'description' => 'Seller-created web campaign.',
                    'badge' => (int) $payload['value'].'% OFF',
                    'currency' => Auth::user()->sellerProfile->default_currency ?? 'BDT',
                    'discount_type' => 'percentage',
                    'discount_value' => ((float) $payload['value']) / 100,
                    'min_spend' => 0,
                    'is_active' => true,
                ]
            );
        } else {
            $coupons = $request->session()->get('web_coupons', []);
            array_unshift($coupons, [
                'code' => Str::upper((string) $payload['code']),
                'value' => (float) $payload['value'],
                'status' => 'Session',
                'usage' => 0,
            ]);
            $request->session()->put('web_coupons', $coupons);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function payoutRequestStore(Request $request): JsonResponse
    {
        $payload = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);
        $payouts = $request->session()->get('web_payout_requests', []);
        array_unshift($payouts, [
            'id' => 'PO-'.now()->format('His'),
            'amount' => (float) $payload['amount'],
            'status' => 'Requested',
            'method' => 'Web payout request',
        ]);
        $request->session()->put('web_payout_requests', $payouts);

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function supportMessageStore(Request $request): JsonResponse
    {
        $payload = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        if (Auth::check()) {
            $thread = ChatThread::query()->firstOrCreate(
                ['kind' => 'support', 'buyer_user_id' => Auth::id(), 'seller_user_id' => null],
                ['uuid' => (string) Str::uuid(), 'subject' => 'Marketplace support', 'status' => 'open', 'last_message_at' => now()]
            );
            ChatMessage::query()->create([
                'uuid' => (string) Str::uuid(),
                'thread_id' => $thread->id,
                'sender_user_id' => Auth::id(),
                'receiver_user_id' => null,
                'sender_role' => Auth::user()?->sellerProfile === null ? 'buyer' : 'seller',
                'body' => (string) $payload['body'],
            ]);
            $thread->last_message_at = now();
            $thread->save();
        } else {
            $messages = $request->session()->get('web_support_messages', []);
            $messages[] = [
                'from' => 'buyer',
                'body' => (string) $payload['body'],
                'time' => now()->format('H:i'),
            ];
            $request->session()->put('web_support_messages', $messages);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function profileUpdate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);

        if (Auth::check()) {
            Auth::user()->fill([
                'display_name' => $payload['name'] ?? Auth::user()->display_name,
                'email' => $payload['email'] ?? Auth::user()->email,
            ])->save();
        } else {
            $request->session()->put('web_profile', [
                'name' => $payload['name'] ?? 'Guest buyer',
                'email' => $payload['email'] ?? '',
                'city' => $payload['city'] ?? '',
            ]);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function businessUpdate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'verification' => ['nullable', 'string', 'max:80'],
        ]);

        $seller = Auth::user()?->sellerProfile;
        if ($seller instanceof SellerProfile) {
            $seller->fill([
                'display_name' => $payload['name'] ?? $seller->display_name,
                'contact_phone' => $payload['phone'] ?? $seller->contact_phone,
                'address_line' => $payload['address'] ?? $seller->address_line,
            ])->save();
        } else {
            $request->session()->put('web_business', $payload);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    private function render(string $mode, string $view, ?int $productId = null): Response
    {
        return Inertia::render('Web/Workspace', [
            'mode' => $mode,
            'view' => $view,
            'productId' => $productId,
            'initialMarketplace' => $this->marketplacePayload(),
        ]);
    }

    private function marketplacePayload(): array
    {
        $user = Auth::user();
        $products = Product::query()
            ->with(['seller_profile', 'category.parent', 'inventoryRecords', 'productVariants.inventoryRecords'])
            ->withCount(['reviews', 'orderItems'])
            ->whereIn('status', ['published', 'active'])
            ->latest('published_at')
            ->latest('id')
            ->limit(36)
            ->get()
            ->map(fn (Product $product): array => $this->productPayload($product))
            ->values()
            ->all();

        $categoryRows = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(64)
            ->get(['id', 'parent_id', 'name', 'slug']);
        $directCategoryProductCounts = Product::query()
            ->whereIn('status', ['published', 'active'])
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
        $childrenByParent = $categoryRows
            ->groupBy(static fn (Category $category): int => (int) ($category->parent_id ?? 0));
        $categoryTotals = [];
        $categoryTotal = function (int $categoryId) use (&$categoryTotal, &$categoryTotals, $childrenByParent, $directCategoryProductCounts): int {
            if (array_key_exists($categoryId, $categoryTotals)) {
                return $categoryTotals[$categoryId];
            }

            $total = (int) ($directCategoryProductCounts[$categoryId] ?? 0);
            foreach (($childrenByParent->get($categoryId) ?? collect()) as $child) {
                $total += $categoryTotal((int) $child->id);
            }

            return $categoryTotals[$categoryId] = $total;
        };
        $categories = $categoryRows
            ->map(function (Category $category) use ($categoryTotal, $directCategoryProductCounts): array {
                return [
                    'id' => (int) $category->id,
                    'parent_id' => $category->parent_id !== null ? (int) $category->parent_id : null,
                    'name' => (string) $category->name,
                    'slug' => (string) $category->slug,
                    'products_count' => $categoryTotal((int) $category->id),
                    'direct_products_count' => (int) ($directCategoryProductCounts[(int) $category->id] ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'source' => 'database',
            'user' => $user === null ? $this->guestUserPayload() : [
                'id' => (int) $user->id,
                'name' => (string) ($user->display_name ?? $user->email ?? 'User'),
                'email' => (string) ($user->email ?? ''),
                'role' => $user->sellerProfile === null ? 'buyer' : 'seller',
                'city' => (string) ($user->sellerProfile?->city ?? ''),
            ],
            'products' => $products,
            'categories' => $categories,
            'cart' => $this->cartPayload(),
            'wishlist' => $this->wishlistPayload(),
            'orders' => $this->ordersPayload(),
            'sellerProducts' => $this->sellerProductsPayload(),
            'coupons' => $this->couponPayload(),
            'payoutRequests' => $this->payoutPayload(),
            'business' => $this->businessPayload(),
            'chats' => $this->chatPayload(),
            'supportTickets' => $this->supportTicketPayload(),
            'featuredVendor' => $this->featuredVendorPayload(),
            'hero' => $this->heroPayload($products),
            'trustItems' => $this->trustItemsPayload(),
            'metrics' => $this->metricsPayload($products),
        ];
    }

    private function productPayload(Product $product): array
    {
        $attributes = is_array($product->attributes_json) ? $product->attributes_json : [];
        $images = $this->productImages($product);
        $image = $images[0] ?? null;
        $basePrice = (float) $product->base_price;
        $manualDiscountPercentage = max(0.0, min(95.0, (float) ($product->discount_percentage ?? 0)));
        $campaign = $this->promotionService->bestCatalogCampaignForProduct($product);
        $campaignDiscountPercentage = $campaign !== null ? (float) ($campaign['discount_percentage'] ?? 0) : 0.0;
        $discountPercentage = max($manualDiscountPercentage, $campaignDiscountPercentage);
        $price = round($basePrice * (1 - ($discountPercentage / 100)), 2);
        $discountLabel = $campaignDiscountPercentage >= $manualDiscountPercentage && $campaign !== null
            ? (string) ($campaign['badge'] ?? $campaign['title'] ?? '')
            : $product->discount_label;
        $productType = (string) ($product->product_type ?? 'physical');
        $stockOnHand = (int) $product->inventoryRecords->sum('stock_on_hand');
        $stockReserved = (int) $product->inventoryRecords->sum('stock_reserved');
        $category = $product->category;
        $parentCategory = $category?->parent;
        $categoryName = (string) ($parentCategory?->name ?? $category?->name ?? 'Marketplace');
        $subcategoryName = $parentCategory !== null ? (string) ($category?->name ?? '') : '';

        return [
            'id' => (int) $product->id,
            'uuid' => (string) ($product->uuid ?? ''),
            'title' => (string) ($product->title ?? 'Untitled listing'),
            'category_id' => (int) ($parentCategory?->id ?? $product->category_id),
            'subcategory_id' => $parentCategory !== null ? (int) $product->category_id : null,
            'category' => $categoryName,
            'subcategory' => $subcategoryName,
            'type' => str_contains(strtolower($productType), 'classified') ? 'Classified' : 'Marketplace',
            'productType' => $productType,
            'productTypeLabel' => $this->productTypeLabel($productType),
            'fulfillmentHint' => $this->productTypeHint($productType),
            'price' => $price,
            'oldPrice' => $discountPercentage > 0 ? $basePrice : $price,
            'discountPercentage' => $discountPercentage,
            'discountLabel' => $discountLabel,
            'activeCampaign' => $campaign,
            'stock' => $stockOnHand,
            'stockReserved' => $stockReserved,
            'stockSold' => (int) $product->inventoryRecords->sum('stock_sold'),
            'availableStock' => max(0, $stockOnHand - $stockReserved),
            'city' => (string) ($product->seller_profile?->city ?? $attributes['product_location'] ?? ''),
            'seller' => (string) ($product->seller_profile?->display_name ?? 'Verified seller'),
            'sellerStatus' => (string) ($product->seller_profile?->verification_status ?? ''),
            'storeStatus' => (string) ($product->seller_profile?->store_status ?? ''),
            'rating' => 4.8,
            'reviewCount' => (int) ($product->reviews_count ?? 0),
            'salesCount' => (int) ($product->order_items_count ?? 0),
            'verified' => in_array((string) $product->seller_profile?->verification_status, ['verified', 'approved'], true),
            'condition' => (string) ($attributes['condition'] ?? 'New'),
            'image' => $image,
            'images' => $images,
            'attributes' => $attributes,
            'attributeRows' => $this->attributeRows($attributes),
            'brand' => (string) ($attributes['brand'] ?? ''),
            'warrantyStatus' => (string) ($attributes['warranty_status'] ?? ''),
            'productLocation' => (string) ($attributes['product_location'] ?? $product->seller_profile?->city ?? ''),
            'variants' => $product->productVariants
                ->map(static fn ($variant): array => [
                    'id' => (int) $variant->id,
                    'title' => (string) ($variant->title ?? 'Variant #'.$variant->id),
                    'sku' => (string) ($variant->sku ?? ''),
                    'priceDelta' => (float) $variant->price_delta,
                    'active' => (bool) $variant->is_active,
                    'stock' => (int) $variant->inventoryRecords->sum('stock_on_hand'),
                ])
                ->values()
                ->all(),
            'tags' => is_array($attributes['tags'] ?? null) ? $attributes['tags'] : ['escrow'],
            'description' => (string) ($product->description ?? 'Verified marketplace listing.'),
            'publishedAt' => $product->published_at?->toIso8601String(),
        ];
    }

    private function cartPayload(): array
    {
        if (Auth::check()) {
            $cart = Cart::query()
                ->where('buyer_user_id', Auth::id())
                ->where('status', 'active')
                ->with(['cartItems.product.seller_profile', 'cartItems.product.category.parent', 'cartItems.product.inventoryRecords'])
                ->latest('id')
                ->first();

            return $cart?->cartItems
                ->map(function (CartItem $item): array {
                    $product = $item->product;
                    return ($product instanceof Product ? $this->productPayload($product) : []) + [
                        'quantity' => (int) $item->quantity,
                    ];
                })
                ->values()
                ->all() ?? [];
        }

        $cart = request()->session()->get('web_cart', []);
        if ($cart === []) {
            return [];
        }

        $dbItems = Product::query()
            ->whereIn('id', array_keys($cart))
            ->with(['seller_profile', 'category.parent', 'inventoryRecords'])
            ->withCount(['reviews', 'orderItems'])
            ->get()
            ->map(fn (Product $product): array => $this->productPayload($product) + [
                'quantity' => (int) ($cart[(int) $product->id] ?? 1),
            ])
            ->keyBy('id');

        $snapshots = request()->session()->get('web_cart_snapshots', []);

        return collect($cart)
            ->map(function ($quantity, int|string $productId) use ($dbItems, $snapshots): array {
                $id = (int) $productId;
                $product = $dbItems->get($id) ?? $this->sanitizeProductSnapshot($snapshots[$id] ?? [], $id);

                return $product + ['quantity' => max(1, (int) $quantity)];
            })
            ->filter(static fn (array $item): bool => isset($item['id']))
            ->values()
            ->all();
    }

    private function wishlistPayload(): array
    {
        if (Auth::check()) {
            return UserWishlistItem::query()
                ->where('user_id', Auth::id())
                ->pluck('product_id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return array_values(array_map('intval', request()->session()->get('web_wishlist', [])));
    }

    private function ordersPayload(): array
    {
        if (Auth::check()) {
            return Order::query()
                ->where(function ($query): void {
                    $query->where('buyer_user_id', Auth::id())
                        ->orWhere('seller_user_id', Auth::id());
                })
                ->with('primaryProduct')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(static fn (Order $order): array => [
                    'id' => (string) ($order->order_number ?? 'SO-'.$order->id),
                    'product' => (string) ($order->primaryProduct?->title ?? $order->product_type ?? 'Marketplace order'),
                    'amount' => (float) $order->net_amount,
                    'status' => method_exists($order->status, 'value') ? $order->status->value : (string) $order->status,
                    'stage' => (string) ($order->fulfillment_state ?? 'Order received'),
                    'eta' => $order->seller_deadline_at?->format('M j') ?? 'Pending',
                    'progress' => $order->completed_at !== null ? 100 : 45,
                ])
                ->values()
                ->all();
        }

        return collect(request()->session()->get('web_guest_orders', []))
            ->reverse()
            ->values()
            ->map(static fn (array $order): array => [
                'id' => (string) $order['id'],
                'product' => (int) ($order['items_count'] ?? 1).' item guest checkout',
                'amount' => (float) ($order['amount'] ?? 0),
                'status' => (string) $order['status'],
                'stage' => 'Create an account to sync this order',
                'eta' => 'Pending',
                'progress' => 20,
            ])
            ->all();
    }

    private function sellerProductsPayload(): array
    {
        $user = Auth::user();
        if ($user?->sellerProfile !== null) {
            return Product::query()
                ->where('seller_profile_id', $user->sellerProfile->id)
                ->with(['seller_profile', 'category.parent', 'inventoryRecords'])
                ->withCount(['reviews', 'orderItems'])
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (Product $product): array => $this->productPayload($product))
                ->values()
                ->all();
        }

        return request()->session()->get('web_seller_products', []);
    }

    private function couponPayload(): array
    {
        $sessionCoupons = request()->session()->get('web_coupons', []);
        $promotions = Promotion::query()
            ->where('campaign_type', 'coupon')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(static fn (Promotion $promotion): array => [
                'code' => (string) $promotion->code,
                'value' => $promotion->discount_type === 'percentage'
                    ? round((float) $promotion->discount_value * 100, 2)
                    : (float) $promotion->discount_value,
                'status' => $promotion->is_active ? 'Active' : 'Paused',
                'usage' => (int) $promotion->used_count,
                'title' => (string) $promotion->title,
                'type' => (string) $promotion->discount_type,
            ])
            ->values()
            ->all();

        return array_values(array_merge($sessionCoupons, $promotions));
    }

    private function payoutPayload(): array
    {
        $seller = Auth::user()?->sellerProfile;
        if ($seller === null) {
            return request()->session()->get('web_payout_requests', []);
        }

        return WithdrawalRequest::query()
            ->where('seller_profile_id', $seller->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(static fn (WithdrawalRequest $withdrawal): array => [
                'id' => 'PO-'.$withdrawal->id,
                'amount' => (float) $withdrawal->requested_amount,
                'status' => method_exists($withdrawal->status, 'value') ? $withdrawal->status->value : (string) $withdrawal->status,
                'method' => 'Wallet payout',
            ])
            ->values()
            ->all();
    }

    private function businessPayload(): array
    {
        $seller = Auth::user()?->sellerProfile;
        if ($seller === null) {
            return request()->session()->get('web_business', [
                'name' => 'Guest seller workspace',
                'phone' => '',
                'address' => '',
                'verification' => 'Guest preview',
            ]);
        }

        return [
            'name' => (string) ($seller->display_name ?? 'Seller store'),
            'phone' => (string) ($seller->contact_phone ?? ''),
            'address' => trim(implode(', ', array_filter([$seller->address_line, $seller->city, $seller->country]))),
            'verification' => (string) $seller->verification_status,
        ];
    }

    private function chatPayload(): array
    {
        if (! Auth::check()) {
            return request()->session()->get('web_support_messages', []);
        }

        return ChatMessage::query()
            ->where(function ($query): void {
                $query->where('sender_user_id', Auth::id())
                    ->orWhere('receiver_user_id', Auth::id());
            })
            ->latest('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(static fn (ChatMessage $message): array => [
                'from' => (int) $message->sender_user_id === (int) Auth::id() ? 'buyer' : 'seller',
                'body' => (string) $message->body,
                'time' => $message->created_at?->format('H:i') ?? '',
            ])
            ->all();
    }

    private function activeCart(): Cart
    {
        return Cart::query()->firstOrCreate(
            ['buyer_user_id' => Auth::id(), 'status' => 'active'],
            ['uuid' => (string) Str::uuid(), 'expires_at' => now()->addDays(14)]
        );
    }

    private function fallbackCategoryId(): int
    {
        return (int) (Category::query()->where('is_active', true)->value('id') ?: Category::query()->value('id') ?: 1);
    }

    private function supportTicketPayload(): array
    {
        if (Auth::check()) {
            return ChatThread::query()
                ->where(function ($query): void {
                    $query->where('buyer_user_id', Auth::id())
                        ->orWhere('seller_user_id', Auth::id());
                })
                ->latest('last_message_at')
                ->limit(8)
                ->get()
                ->map(static fn (ChatThread $thread): array => [
                    'id' => 'SUP-'.$thread->id,
                    'subject' => (string) ($thread->subject ?? 'Marketplace support'),
                    'status' => (string) $thread->status,
                ])
                ->values()
                ->all();
        }

        $tickets = request()->session()->get('web_support_tickets', []);
        if ($tickets !== []) {
            return $tickets;
        }

        $messages = request()->session()->get('web_support_messages', []);
        if ($messages !== []) {
            return [[
                'id' => 'SUP-'.now()->format('His'),
                'subject' => 'Marketplace support conversation',
                'status' => 'Open',
            ]];
        }

        return [];
    }

    private function featuredVendorPayload(): ?array
    {
        $seller = SellerProfile::query()
            ->withCount(['products', 'orderItems'])
            ->whereIn('verification_status', ['verified', 'approved'])
            ->orderByDesc('order_items_count')
            ->orderByDesc('products_count')
            ->first();

        if (! $seller instanceof SellerProfile) {
            return null;
        }

        return [
            'name' => (string) ($seller->display_name ?? 'Verified seller'),
            'description' => (string) ($seller->storefront?->description ?? 'Verified marketplace seller with protected checkout and active catalog operations.'),
            'image' => $this->imageUrl($seller->banner_image_url),
            'successRate' => $seller->verification_status === 'verified' ? '100%' : 'Verified',
            'sales' => (string) (int) $seller->order_items_count,
        ];
    }

    private function heroPayload(array $products): array
    {
        $featured = $products[0] ?? null;
        $second = $products[1] ?? $featured;
        $third = $products[2] ?? $featured;

        return [
            'eyebrow' => $this->couponPayload()[0]['title'] ?? 'Live marketplace',
            'title' => $featured === null ? 'Discover verified marketplace deals' : 'Featured: '.$featured['title'],
            'description' => $featured === null
                ? 'Browse active products, classified ads, seller offers, escrow orders, and delivery tracking from one responsive web workspace.'
                : 'Live catalog listing from '.$featured['seller'].' with protected checkout, stock visibility, and seller trust signals.',
            'image' => $featured['image'] ?? null,
            'panels' => array_values(array_filter([
                $second === null ? null : [
                    'eyebrow' => $second['productTypeLabel'] ?? $second['type'],
                    'title' => $second['title'],
                    'cta' => $second['category'],
                    'image' => $second['image'],
                ],
                $third === null ? null : [
                    'eyebrow' => $third['productTypeLabel'] ?? $third['type'],
                    'title' => $third['title'],
                    'cta' => $third['category'],
                    'image' => $third['image'],
                ],
            ])),
        ];
    }

    private function trustItemsPayload(): array
    {
        return [
            ['title' => 'Escrow Protection', 'body' => 'Funds held securely until approved delivery.'],
            ['title' => 'Fulfillment Aware', 'body' => 'Physical, digital, instant, and service listings stay clearly labeled.'],
            ['title' => 'Verified Vendors', 'body' => SellerProfile::query()->whereIn('verification_status', ['verified', 'approved'])->count().' verified sellers.'],
            ['title' => 'Support Desk', 'body' => 'Buyer, seller, dispute, and order conversations in one workspace.'],
        ];
    }

    private function metricsPayload(array $products): array
    {
        return [
            'products' => count($products),
            'categories' => Category::query()->where('is_active', true)->count(),
            'orders' => Order::query()->count(),
            'sellers' => SellerProfile::query()->count(),
        ];
    }

    private function guestUserPayload(): array
    {
        $profile = request()->session()->get('web_profile', []);

        return [
            'id' => null,
            'name' => (string) ($profile['name'] ?? 'Guest buyer'),
            'email' => (string) ($profile['email'] ?? ''),
            'role' => 'buyer',
            'city' => (string) ($profile['city'] ?? ''),
        ];
    }

    private function imageUrl(?string $image): ?string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return null;
        }
        $path = parse_url($image, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/api/v1/media/')) {
            return $path;
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return '/api/v1/media/'.str_replace('%2F', '/', rawurlencode(ltrim($image, '/')));
    }

    /**
     * @return list<string>
     */
    private function productImages(Product $product): array
    {
        $rawImages = is_array($product->images_json) ? $product->images_json : [];
        if ($product->image_url !== null && trim((string) $product->image_url) !== '') {
            array_unshift($rawImages, (string) $product->image_url);
        }

        $seen = [];
        $images = [];
        foreach ($rawImages as $image) {
            $raw = trim((string) $image);
            if ($raw === '' || isset($seen[$raw])) {
                continue;
            }
            $seen[$raw] = true;
            $images[] = $this->imageUrl($raw);
        }

        return $images;
    }

    private function productTypeLabel(string $type): string
    {
        return match ($type) {
            'physical' => 'Physical product',
            'digital' => 'Digital product',
            'instant_delivery' => 'Instant delivery',
            'service' => 'Service',
            default => ucfirst(str_replace('_', ' ', $type ?: 'Marketplace')),
        };
    }

    private function productTypeHint(string $type): string
    {
        return match ($type) {
            'physical' => 'Ships from the seller with inventory and delivery tracking.',
            'digital' => 'Delivered digitally after checkout through seller handoff.',
            'instant_delivery' => 'Prepared for instant digital fulfillment after payment.',
            'service' => 'Fulfilled as a service through the seller workflow.',
            default => 'Protected marketplace checkout with seller support.',
        };
    }

    /**
     * @param array<string, mixed> $attributes
     * @return list<array{label: string, value: string}>
     */
    private function attributeRows(array $attributes): array
    {
        $rows = [];
        foreach ($attributes as $key => $value) {
            if ($key === 'tags') {
                continue;
            }
            $rows[] = [
                'label' => ucfirst(str_replace('_', ' ', (string) $key)),
                'value' => is_scalar($value) || $value === null
                    ? (string) ($value ?? '—')
                    : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        return $rows;
    }

    private function sanitizeProductSnapshot(mixed $snapshot, int $productId): array
    {
        if (! is_array($snapshot)) {
            return [];
        }

        return [
            'id' => $productId,
            'title' => Str::limit((string) ($snapshot['title'] ?? 'Marketplace listing'), 255, ''),
            'category' => Str::limit((string) ($snapshot['category'] ?? 'Marketplace'), 80, ''),
            'subcategory' => Str::limit((string) ($snapshot['subcategory'] ?? ''), 80, ''),
            'subcategory_id' => isset($snapshot['subcategory_id']) && is_numeric($snapshot['subcategory_id']) ? (int) $snapshot['subcategory_id'] : null,
            'type' => Str::limit((string) ($snapshot['type'] ?? 'Marketplace'), 40, ''),
            'price' => (float) ($snapshot['price'] ?? 0),
            'oldPrice' => (float) ($snapshot['oldPrice'] ?? 0),
            'stock' => max(0, (int) ($snapshot['stock'] ?? 1)),
            'city' => Str::limit((string) ($snapshot['city'] ?? ''), 80, ''),
            'seller' => Str::limit((string) ($snapshot['seller'] ?? 'Verified seller'), 120, ''),
            'rating' => (float) ($snapshot['rating'] ?? 4.8),
            'verified' => (bool) ($snapshot['verified'] ?? false),
            'condition' => Str::limit((string) ($snapshot['condition'] ?? 'New'), 40, ''),
            'image' => $this->imageUrl((string) ($snapshot['image'] ?? '')),
            'tags' => array_values(array_slice(array_filter(array_map('strval', (array) ($snapshot['tags'] ?? ['escrow']))), 0, 5)),
            'description' => Str::limit((string) ($snapshot['description'] ?? 'Marketplace listing.'), 5000, ''),
        ];
    }
}

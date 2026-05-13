<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Domain\Commands\Order\AddOrderShippingDetailsCommand;
use App\Domain\Commands\Order\CreateOrderCommand;
use App\Domain\Commands\Product\CreateProductCommand;
use App\Domain\Enums\ProductType;
use App\Domain\Commands\WalletTopUp\RequestWalletTopUpCommand;
use App\Domain\Commands\Withdrawal\RequestWithdrawalCommand;
use App\Domain\Enums\WalletAccountStatus;
use App\Domain\Enums\WalletType;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Domain\Exceptions\WithdrawalValidationFailedException;
use App\Domain\Value\CartLineItem;
use App\Domain\Value\CartSnapshot;
use App\Domain\Value\ProductDraft;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\BuyerReview;
use App\Models\DigitalDeliveryFile;
use App\Models\AuditLog;
use App\Models\InventoryRecord;
use App\Models\DisputeCase;
use App\Models\KycVerification;
use App\Models\KycDocument;
use App\Models\KycSetting;
use App\Models\KycStatusHistory;
use App\Models\KycVerificationLog;
use App\Models\KycVerificationProvider;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderMessageAttachment;
use App\Models\OrderItem;
use App\Models\OrderStateTransition;
use App\Models\PayoutAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SellerShippingMethod;
use App\Models\SellerProfile;
use App\Models\SellerWarehouse;
use App\Models\ShippingMethod;
use App\Models\StockMovement;
use App\Models\Storefront;
use App\Models\PushDevice;
use App\Models\UserWishlistItem;
use App\Models\UserAddress;
use App\Models\UserNotificationPreference;
use App\Models\UserPaymentMethod;
use App\Models\Wallet;
use App\Models\EscrowAccount;
use App\Models\EscrowEvent;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTopUpRequest;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Services\Order\OrderService;
use App\Services\Order\EscrowOrderDetailService;
use App\Services\Order\OrderMessageService;
use App\Services\DigitalDelivery\DigitalDeliveryService;
use App\Services\Product\ProductService;
use App\Services\Promotion\PromotionService;
use App\Services\Kyc\KycProviderService;
use App\Services\Marketplace\ReviewService as MarketplaceReviewService;
use App\Services\Dispute\DisputeService;
use App\Services\WalletLedger\WalletLedgerService;
use App\Services\Withdrawal\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\UserSeller\UserSellerService;
use App\Services\WalletTopUp\WalletTopUpRequestService;
use App\Support\Notifications\NotificationPresenter;
use Inertia\Inertia;
use Inertia\Response;

final class MarketplaceController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly OrderService $orderService,
        private readonly PromotionService $promotionService = new PromotionService(),
        private readonly WithdrawalService $withdrawalService = new WithdrawalService(),
        private readonly KycProviderService $kycProviderService = new KycProviderService(),
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

    public function seller(): Response|RedirectResponse
    {
        if (! Auth::check()) {
            request()->session()->put('url.intended', '/seller/dashboard');
            request()->session()->put('auth.panel', 'seller');

            return redirect()->route('login');
        }

        if (Auth::user()?->sellerProfile === null) {
            request()->session()->put('auth.panel', 'seller');

            return redirect()->route('web.register');
        }

        return $this->render('seller', 'seller-dashboard');
    }

    public function marketplace(): Response
    {
        return $this->render('buyer', 'marketplace');
    }

    public function product(int $productId): Response
    {
        $recent = collect(request()->session()->get('web_recently_viewed', []))
            ->map(static fn ($id): int => (int) $id)
            ->reject(static fn (int $id): bool => $id === $productId)
            ->prepend($productId)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->take(16)
            ->values()
            ->all();
        request()->session()->put('web_recently_viewed', $recent);

        return $this->render('buyer', 'product', $productId);
    }

    public function buyerView(string $view): Response|RedirectResponse
    {
        if (in_array($view, [
            'dashboard', 'checkout', 'orders', 'order-details', 'escrow-orders', 'refund-requests', 'return-requests', 'replacement-requests',
            'wishlist', 'saved-items', 'favorite-stores', 'recently-viewed', 'profile', 'profile-settings', 'security-settings',
            'address-book', 'wallet', 'top-up-history', 'transaction-history', 'referral-dashboard', 'loyalty-rewards',
            'coupons-promotions', 'support', 'support-tickets', 'notifications', 'messages', 'product-reviews', 'seller-reviews',
            'kyc-verification', 'device-management',
        ], true) && ! Auth::check()) {
            request()->session()->put('url.intended', '/'.$view);
            request()->session()->put('auth.panel', 'buyer');

            return redirect()->route('login');
        }

        return $this->render('buyer', $view);
    }

    public function buyerOrderShow(Order $order): Response|RedirectResponse
    {
        $this->authorizeOrderParticipant($order, 'buyer');

        request()->query->set('order', (string) $order->id);

        return $this->render('buyer', 'order-details');
    }

    public function sellerView(string|int|null $view = null, ?string $routeView = null): Response|RedirectResponse
    {
        $view = $routeView ?? $view;

        if (! Auth::check()) {
            request()->session()->put('url.intended', '/seller/'.($view === null ? 'dashboard' : $view));
            request()->session()->put('auth.panel', 'seller');

            return redirect()->route('login');
        }

        if (Auth::user()?->sellerProfile === null) {
            request()->session()->put('auth.panel', 'seller');

            return redirect()->route('web.register');
        }

        return $this->render('seller', $view === null ? 'seller-dashboard' : 'seller-'.$view);
    }

    public function sellerOrderShow(Order $order): Response|RedirectResponse
    {
        $this->authorizeOrderParticipant($order, 'seller');

        request()->query->set('order', (string) $order->id);

        return $this->render('seller', 'seller-order-details');
    }

    public function cartAdd(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'product_snapshot' => ['nullable', 'array'],
        ]);
        $productId = (int) $payload['product_id'];
        $product = Product::query()
            ->with([
                'seller_profile.user',
                'category.parent',
                'inventoryRecords',
                'productVariants.inventoryRecords',
                'reviews' => static fn ($query) => $query->where('status', 'visible')->with('buyer')->latest(),
            ])
            ->withCount([
                'reviews' => static fn ($query) => $query->where('status', 'visible'),
                'orderItems',
            ])
            ->withAvg([
                'reviews as reviews_avg_rating' => static fn ($query) => $query->where('status', 'visible'),
            ], 'rating')
            ->whereKey($productId)
            ->first();
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

    public function orderEscrowDetail(Request $request, Order $order): JsonResponse
    {
        $context = $request->query('context');
        $viewerId = $this->authorizeOrderParticipant($order, in_array($context, ['buyer', 'seller'], true) ? (string) $context : null);
        $detail = app(EscrowOrderDetailService::class)->build($order, $viewerId);

        return response()->json(['ok' => true, 'detail' => $detail]);
    }

    public function buyerOrderApiShow(Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order, 'buyer');

        return response()->json([
            'ok' => true,
            'context' => 'buyer',
            'detail' => app(EscrowOrderDetailService::class)->build($order, $viewerId),
        ]);
    }

    public function sellerOrderApiShow(Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order, 'seller');

        return response()->json([
            'ok' => true,
            'context' => 'seller',
            'detail' => app(EscrowOrderDetailService::class)->build($order, $viewerId),
        ]);
    }

    public function buyerOrderRelease(Request $request, Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order, 'buyer');
        app(DigitalDeliveryService::class)->confirmAccepted($order, $viewerId, 'web:buyer:release:'.$order->id);
        $this->orderService->completeOrder(new \App\Domain\Commands\Order\CompleteOrderCommand(
            orderId: (int) $order->id,
            actorUserId: $viewerId,
            correlationId: 'web:buyer:release:'.$order->id,
        ));

        return response()->json([
            'ok' => true,
            'marketplace' => $this->marketplacePayload(),
            'escrow_order_detail' => app(EscrowOrderDetailService::class)->build($order->fresh(), $viewerId),
        ]);
    }

    public function buyerOrderReviewStore(Request $request, Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order, 'buyer');
        $payload = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_type' => ['nullable', 'string', 'in:good,neutral,bad'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'category_ratings' => ['nullable', 'array'],
        ]);

        $order->loadMissing(['orderItems']);
        $status = $order->status instanceof \BackedEnum ? (string) $order->status->value : (string) $order->status;
        abort_unless($status === 'completed' || $order->completed_at !== null || (string) $order->escrow_status === 'released', 422, 'Reviews can be submitted only after the order is completed.');

        $item = $order->orderItems->first();
        abort_unless($item instanceof OrderItem, 422, 'This order does not have a reviewable item.');

        $review = Review::query()
            ->where('order_item_id', (int) $item->id)
            ->where('buyer_user_id', $viewerId)
            ->first();

        if (! $review instanceof Review) {
            $review = new Review([
                'uuid' => (string) Str::uuid(),
                'order_item_id' => (int) $item->id,
                'buyer_user_id' => $viewerId,
            ]);
        }

        $review->seller_profile_id = (int) ($item->seller_profile_id ?? $order->seller_profile_id ?? 0);
        $review->product_id = (int) ($item->product_id ?? $order->primary_product_id ?? 0);
        $review->rating = (int) $payload['rating'];
        $review->feedback_type = (string) ($payload['feedback_type'] ?? $this->feedbackTypeForRating((int) $payload['rating']));
        $review->comment = trim((string) ($payload['comment'] ?? ''));
        $review->tags = array_values(array_slice(array_filter((array) ($payload['tags'] ?? [])), 0, 12));
        $review->status = 'visible';
        $review->helpful_count = (int) ($review->helpful_count ?? 0);
        $review->save();
        app(MarketplaceReviewService::class)->store($request->user(), [
            'reviewer_role' => 'buyer',
            'reviewed_role' => 'seller',
            'reviewed_id' => (int) $review->seller_profile_id,
            'order_id' => (int) $order->id,
            'rating' => (int) $payload['rating'],
            'feedback_type' => (string) $review->feedback_type,
            'comment' => (string) ($review->comment ?? ''),
            'tags' => $review->tags ?? [],
            'category_ratings' => $payload['category_ratings'] ?? [],
        ]);

        return response()->json([
            'ok' => true,
            'review' => [
                'id' => (int) $review->id,
                'rating' => (int) $review->rating,
                'comment' => (string) ($review->comment ?? ''),
                'createdAt' => $review->created_at?->toIso8601String(),
            ],
            'marketplace' => $this->marketplacePayload(),
            'escrow_order_detail' => app(EscrowOrderDetailService::class)->build($order->fresh(), $viewerId),
        ]);
    }

    public function sellerBuyerReviewStore(Request $request, Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order, 'seller');
        $payload = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_type' => ['nullable', 'string', 'in:good,neutral,bad'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'category_ratings' => ['nullable', 'array'],
        ]);

        $status = $order->status instanceof \BackedEnum ? (string) $order->status->value : (string) $order->status;
        abort_unless($status === 'completed' || $order->completed_at !== null || (string) $order->escrow_status === 'released', 422, 'Buyer reviews can be submitted only after the order is completed.');

        $order->loadMissing(['orderItems']);
        $sellerProfileId = (int) ($order->orderItems->first()?->seller_profile_id ?? 0);
        if ($sellerProfileId <= 0) {
            $sellerProfileId = (int) SellerProfile::query()->where('user_id', $viewerId)->value('id');
        }
        abort_unless($sellerProfileId > 0, 403);

        $review = BuyerReview::query()
            ->where('order_id', (int) $order->id)
            ->where('seller_profile_id', $sellerProfileId)
            ->first();

        if (! $review instanceof BuyerReview) {
            $review = new BuyerReview([
                'uuid' => (string) Str::uuid(),
                'order_id' => (int) $order->id,
                'seller_user_id' => $viewerId,
                'seller_profile_id' => $sellerProfileId,
                'buyer_user_id' => (int) $order->buyer_user_id,
            ]);
        }

        $review->rating = (int) $payload['rating'];
        $review->feedback_type = (string) ($payload['feedback_type'] ?? $this->feedbackTypeForRating((int) $payload['rating']));
        $review->comment = trim((string) ($payload['comment'] ?? ''));
        $review->tags = array_values(array_slice(array_filter((array) ($payload['tags'] ?? [])), 0, 12));
        $review->status = 'visible';
        $review->save();
        app(MarketplaceReviewService::class)->store($request->user(), [
            'reviewer_role' => 'seller',
            'reviewed_role' => 'buyer',
            'reviewed_id' => (int) $order->buyer_user_id,
            'order_id' => (int) $order->id,
            'rating' => (int) $payload['rating'],
            'feedback_type' => (string) $review->feedback_type,
            'comment' => (string) ($review->comment ?? ''),
            'tags' => $review->tags ?? [],
            'category_ratings' => $payload['category_ratings'] ?? [],
        ]);

        return response()->json([
            'ok' => true,
            'buyer_review' => [
                'id' => (int) $review->id,
                'rating' => (int) $review->rating,
                'comment' => (string) ($review->comment ?? ''),
                'createdAt' => $review->created_at?->toIso8601String(),
            ],
            'marketplace' => $this->marketplacePayload(),
            'escrow_order_detail' => app(EscrowOrderDetailService::class)->build($order->fresh(), $viewerId),
        ]);
    }

    public function reviewHelpfulStore(Request $request, Review $review): JsonResponse
    {
        $viewerId = (int) $request->user()->id;
        $review->loadMissing(['seller_profile']);

        abort_unless((string) $review->status === 'visible', 404);
        abort_if((int) ($review->seller_profile?->user_id ?? 0) === $viewerId, 422, 'Sellers cannot mark their own store reviews as helpful.');

        DB::transaction(function () use ($review, $viewerId): void {
            $inserted = DB::table('review_helpful_votes')->insertOrIgnore([
                'review_id' => (int) $review->id,
                'user_id' => $viewerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted > 0) {
                $review->increment('helpful_count');
            }
        });

        return response()->json([
            'ok' => true,
            'review' => [
                'id' => (int) $review->id,
                'helpfulCount' => (int) $review->fresh()->helpful_count,
            ],
            'marketplace' => $this->marketplacePayload(),
        ]);
    }

    public function buyerOrderDisputeStore(Request $request, Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order, 'buyer');
        $payload = $request->validate([
            'reason_code' => ['required', 'string', 'max:80'],
        ]);

        app(DisputeService::class)->openDispute(new \App\Domain\Commands\Dispute\OpenDisputeCommand(
            orderId: (int) $order->id,
            orderItemId: null,
            openedByUserId: $viewerId,
            reasonCode: (string) $payload['reason_code'],
            correlationId: 'web:buyer:dispute:'.$order->id,
            idempotencyKey: 'web:buyer:dispute:'.$order->id,
        ));

        return response()->json([
            'ok' => true,
            'marketplace' => $this->marketplacePayload(),
            'escrow_order_detail' => app(EscrowOrderDetailService::class)->build($order->fresh(), $viewerId),
        ]);
    }

    public function sellerOrderDeliveryStore(Request $request, Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order, 'seller');
        $payload = $request->validate([
            'delivery_message' => ['nullable', 'string', 'max:5000'],
            'external_delivery_url' => ['nullable', 'url', 'max:1000'],
            'delivery_version' => ['nullable', 'string', 'max:32'],
            'files.*' => ['nullable', 'file', 'max:25600'],
        ]);
        $deliveryNote = trim((string) ($payload['delivery_message'] ?? ''));
        if ($deliveryNote === '') {
            $deliveryNote = 'Marked delivered by seller.';
        }

        $productType = ProductType::normalize((string) $order->product_type);
        if ($productType === ProductType::Physical) {
            $proofFiles = array_values($request->file('files', []));
            $trackingId = trim((string) ($payload['delivery_version'] ?? ''));
            if ($trackingId === '' || strtolower($trackingId) === 'v1') {
                $trackingId = 'SELL-'.$order->id.'-'.now()->format('YmdHis');
            }
            $trackingUrl = trim((string) ($payload['external_delivery_url'] ?? ''));
            $alreadyShipped = $order->shipped_at !== null
                || trim((string) ($order->tracking_id ?? '')) !== ''
                || in_array((string) ($order->delivery_status ?? ''), ['shipped', 'in_transit', 'out_for_delivery'], true);

            app(OrderService::class)->addShippingDetails(new AddOrderShippingDetailsCommand(
                orderId: (int) $order->id,
                actorUserId: $viewerId,
                courierCompany: 'Seller delivery',
                trackingId: $trackingId,
                trackingUrl: $trackingUrl !== '' ? $trackingUrl : null,
                shippingNote: $deliveryNote,
                shippedAtIso: now()->toIso8601String(),
                correlationId: 'web:seller:delivery:'.$order->id,
            ));
            $freshOrder = $order->fresh();
            $freshOrder->tracking_url = $trackingUrl !== '' ? $trackingUrl : null;
            $freshOrder->delivery_note = $deliveryNote;
            $freshOrder->delivery_version = $trackingId;
            $freshOrder->delivery_files_count = count($proofFiles);
            if ($alreadyShipped) {
                $freshOrder->delivery_status = 'delivered';
                $freshOrder->delivered_at = $freshOrder->delivered_at ?: now();
                $freshOrder->delivery_submitted_at = $freshOrder->delivery_submitted_at ?: now();
            } else {
                $freshOrder->delivery_status = 'shipped';
            }
            $freshOrder->save();

            app(OrderMessageService::class)->sendMessage(
                order: $freshOrder,
                senderUserId: $viewerId,
                body: $deliveryNote,
                attachments: $proofFiles,
                artifactType: 'physical_delivery_submission',
                isDeliveryProof: true,
            );
        } else {
            app(DigitalDeliveryService::class)->submitDelivery(
                order: $order,
                actorUserId: $viewerId,
                note: $deliveryNote,
                externalUrl: $payload['external_delivery_url'] ?? null,
                version: $payload['delivery_version'] ?? null,
                files: array_values($request->file('files', [])),
                correlationId: 'web:seller:delivery:'.$order->id,
            );
        }

        return response()->json([
            'ok' => true,
            'marketplace' => $this->marketplacePayload(),
            'escrow_order_detail' => app(EscrowOrderDetailService::class)->build($order->fresh(), $viewerId),
        ]);
    }

    public function orderEscrowMessageStore(Request $request, Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order);
        $payload = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'artifact_type' => ['nullable', 'string', 'max:64'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $message = app(OrderMessageService::class)->sendMessage(
            order: $order,
            senderUserId: $viewerId,
            body: (string) ($payload['body'] ?? ''),
            attachments: array_values($request->file('attachments', [])),
            artifactType: isset($payload['artifact_type']) ? (string) $payload['artifact_type'] : null,
        );

        return response()->json([
            'ok' => true,
            'message' => $message,
            'marketplace' => $this->marketplacePayload(),
            'escrow_order_detail' => app(EscrowOrderDetailService::class)->build($order->fresh(), $viewerId),
        ]);
    }

    public function orderEscrowMessagesRead(Order $order): JsonResponse
    {
        $viewerId = $this->authorizeOrderParticipant($order);
        app(OrderMessageService::class)->markRead($order, $viewerId);

        return response()->json(['ok' => true]);
    }

    public function deliveryFileDownload(Request $request, DigitalDeliveryFile $digitalDeliveryFile)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $order = Order::query()->findOrFail((int) $digitalDeliveryFile->order_id);
        $this->authorizeOrderParticipant($order);

        abort_unless(Storage::disk((string) $digitalDeliveryFile->disk)->exists((string) $digitalDeliveryFile->path), 404);

        return Storage::disk((string) $digitalDeliveryFile->disk)->download(
            (string) $digitalDeliveryFile->path,
            (string) $digitalDeliveryFile->original_name,
        );
    }

    public function orderMessageAttachmentDownload(Request $request, OrderMessageAttachment $orderMessageAttachment)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $order = Order::query()->findOrFail((int) $orderMessageAttachment->order_id);
        $this->authorizeOrderParticipant($order);

        abort_unless(Storage::disk((string) $orderMessageAttachment->disk)->exists((string) $orderMessageAttachment->path), 404);

        return Storage::disk((string) $orderMessageAttachment->disk)->download(
            (string) $orderMessageAttachment->path,
            (string) $orderMessageAttachment->original_name,
        );
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
            'address_id' => ['nullable', 'integer'],
            'shipping_address_line' => ['nullable', 'string', 'max:5000'],
            'recipient_name' => ['nullable', 'string', 'max:191'],
            'shipping_phone' => ['nullable', 'string', 'max:40'],
            'shipping_method' => ['nullable', 'string', 'in:standard,express'],
            'payment_method' => ['nullable', 'string', Rule::in(['wallet', 'manual'])],
            'payment_method_id' => ['nullable', 'integer'],
            'payment_reference' => ['nullable', 'string', 'max:191'],
        ]);

        if (Auth::check()) {
            $cart = $this->activeCart();
            $cart->load('cartItems.product.seller_profile');
            if ($cart->cartItems->isEmpty()) {
                return response()->json(['ok' => false, 'message' => 'Cart is empty.'], 422);
            }
            $requiresShipping = $cart->cartItems->contains(static fn (CartItem $item): bool => (string) ($item->product?->product_type ?? 'physical') === 'physical');
            $selectedAddress = null;
            if (! empty($payload['address_id'])) {
                $selectedAddress = UserAddress::query()
                    ->where('user_id', (int) Auth::id())
                    ->whereKey((int) $payload['address_id'])
                    ->first();

                if (! $selectedAddress instanceof UserAddress) {
                    return response()->json(['ok' => false, 'message' => 'The selected address is not available for this account.'], 422);
                }
            }

            $shippingAddressLine = trim((string) ($selectedAddress?->address_line ?? ($payload['shipping_address_line'] ?? '')));
            $shippingRecipientName = trim((string) ($selectedAddress?->recipient_name ?? ($payload['recipient_name'] ?? '')));
            $shippingPhone = trim((string) ($selectedAddress?->phone ?? ($payload['shipping_phone'] ?? '')));
            if ($requiresShipping && $shippingAddressLine === '') {
                return response()->json(['ok' => false, 'message' => 'A shipping address is required before placing this order.'], 422);
            }

            $selectedPaymentMethod = null;
            if (! empty($payload['payment_method_id'])) {
                $selectedPaymentMethod = UserPaymentMethod::query()
                    ->where('user_id', (int) Auth::id())
                    ->whereKey((int) $payload['payment_method_id'])
                    ->first();

                if (! $selectedPaymentMethod instanceof UserPaymentMethod) {
                    return response()->json(['ok' => false, 'message' => 'The selected payment method is not available for this account.'], 422);
                }
            }

            $paymentMethod = (string) ($payload['payment_method'] ?? 'wallet');
            $orderResults = [];

            try {
                DB::transaction(function () use ($cart, $payload, $selectedAddress, $shippingRecipientName, $shippingAddressLine, $shippingPhone, $paymentMethod, $selectedPaymentMethod, &$orderResults): void {
                    $groups = $cart->cartItems
                        ->groupBy(static function (CartItem $item): string {
                            $type = (string) ($item->product?->product_type ?? 'physical');

                            return ((int) $item->seller_profile_id).'|'.$type;
                        })
                        ->values();

                    foreach ($groups as $index => $items) {
                        $lines = $items->map(static fn (CartItem $item): CartLineItem => new CartLineItem(
                            productId: (int) $item->product_id,
                            productVariantId: $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                            sellerProfileId: (int) $item->seller_profile_id,
                            quantity: (int) $item->quantity,
                            unitPrice: (string) $item->unit_price_snapshot,
                            currency: (string) $item->currency_snapshot,
                        ))->values()->all();

                        $orderResult = $this->orderService->createOrder(new CreateOrderCommand(
                            buyerUserId: (int) Auth::id(),
                            cartSnapshot: new CartSnapshot($lines),
                            idempotencyKey: (string) Str::uuid(),
                            shippingMethod: (string) ($payload['shipping_method'] ?? 'standard'),
                            shippingMethodProvided: true,
                            shippingAddressId: $selectedAddress?->id !== null ? (string) $selectedAddress->id : null,
                            shippingRecipientName: $shippingRecipientName !== '' ? $shippingRecipientName : null,
                            shippingAddressLine: $shippingAddressLine !== '' ? $shippingAddressLine : null,
                            shippingPhone: $shippingPhone !== '' ? $shippingPhone : null,
                        ));

                        $orderId = (int) ($orderResult['order_id'] ?? 0);
                        $correlationId = (string) Str::uuid();

                        if ($paymentMethod === 'manual') {
                            $reference = trim((string) ($payload['payment_reference'] ?? ''));
                            $this->orderService->payOrderWithManualMethod(
                                orderId: $orderId,
                                actorUserId: (int) Auth::id(),
                                provider: 'bank',
                                providerReference: $reference !== ''
                                    ? ($reference.(count($groups) > 1 ? '-'.($index + 1) : ''))
                                    : ($selectedPaymentMethod instanceof UserPaymentMethod
                                        ? 'WEB-MANUAL-'.$selectedPaymentMethod->id.'-'.Str::slug($selectedPaymentMethod->label ?: $selectedPaymentMethod->kind).(count($groups) > 1 ? '-'.($index + 1) : '')
                                        : 'WEB-MANUAL-'.$orderResult['order_number']),
                                correlationId: $correlationId,
                            );
                        } else {
                            $this->orderService->payOrderWithWallet(
                                orderId: $orderId,
                                actorUserId: (int) Auth::id(),
                                correlationId: $correlationId,
                            );
                        }

                        $orderResults[] = $orderResult;
                    }
                });
            } catch (OrderValidationFailedException $exception) {
                return response()->json([
                    'ok' => false,
                    'message' => $this->friendlyOrderValidationMessage($exception->reasonCode),
                    'reason_code' => $exception->reasonCode,
                ], 422);
            }

            $cart->cartItems()->delete();
            $cart->status = 'checked_out';
            $cart->save();

            $orderId = (int) ($orderResults[0]['order_id'] ?? 0);
            $redirectUrl = '/buyer/orders/'.$orderId;
        } else {
            $orders = $request->session()->get('web_guest_orders', []);
            $cart = $request->session()->get('web_cart', []);
            if ($cart === []) {
                return response()->json(['ok' => false, 'message' => 'Cart is empty.'], 422);
            }
            $hasProtectedDigital = collect($this->cartPayload())->contains(static fn (array $item): bool => in_array((string) ($item['productType'] ?? 'physical'), ['digital', 'service'], true));
            if ($hasProtectedDigital) {
                $request->session()->put('url.intended', '/checkout');

                return response()->json(['ok' => false, 'message' => 'Login or register before placing a protected digital order.'], 401);
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
            $redirectUrl = '/orders';
        }

        return response()->json([
            'ok' => true,
            'marketplace' => $this->marketplacePayload(),
            'order_id' => $orderId ?? null,
            'order_ids' => collect($orderResults ?? [])->pluck('order_id')->map(static fn ($id): int => (int) $id)->values()->all(),
            'redirect_url' => $redirectUrl ?? '/escrow-orders',
        ]);
    }

    private function friendlyOrderValidationMessage(string $reasonCode): string
    {
        return match ($reasonCode) {
            'multi_seller_escrow_not_supported' => 'This cart contains products from multiple sellers. We could not create separate protected orders. Please try again or checkout one seller at a time.',
            'mixed_product_type_checkout_not_supported' => 'This cart contains different fulfillment types. Please checkout physical, digital, and service items separately.',
            'mixed_currency_checkout_not_supported' => 'This cart contains multiple currencies. Please checkout one currency at a time.',
            'self_purchase_not_allowed' => 'You cannot purchase your own listing.',
            'product_not_found' => 'One item in your cart is no longer available. Please refresh your cart and try again.',
            'invalid_quantity' => 'One item in your cart has an invalid quantity. Please update your cart and try again.',
            default => 'We could not validate this order. Please review your cart and try again.',
        };
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

    public function buyerPaymentMethodStore(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401);

        $payload = $this->validatedBuyerPaymentMethodPayload($request);
        $method = app(UserSellerService::class)->createBuyerPaymentMethod((int) Auth::id(), $payload);

        return response()->json([
            'ok' => true,
            'payment_method' => $method,
            'marketplace' => $this->marketplacePayload(),
        ]);
    }

    public function buyerPaymentMethodUpdate(Request $request, UserPaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless(Auth::check(), 401);
        abort_unless((int) $paymentMethod->user_id === (int) Auth::id(), 403);

        $payload = $this->validatedBuyerPaymentMethodPayload($request);
        $method = app(UserSellerService::class)->updateBuyerPaymentMethod((int) Auth::id(), (int) $paymentMethod->id, $payload);

        return response()->json([
            'ok' => true,
            'payment_method' => $method,
            'marketplace' => $this->marketplacePayload(),
        ]);
    }

    public function buyerPaymentMethodDefault(UserPaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless(Auth::check(), 401);
        abort_unless((int) $paymentMethod->user_id === (int) Auth::id(), 403);

        $method = app(UserSellerService::class)->setDefaultBuyerPaymentMethod((int) Auth::id(), (int) $paymentMethod->id);

        return response()->json([
            'ok' => true,
            'payment_method' => $method,
            'marketplace' => $this->marketplacePayload(),
        ]);
    }

    public function buyerPaymentMethodDestroy(UserPaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless(Auth::check(), 401);
        abort_unless((int) $paymentMethod->user_id === (int) Auth::id(), 403);

        app(UserSellerService::class)->deleteBuyerPaymentMethod((int) Auth::id(), (int) $paymentMethod->id);

        return response()->json([
            'ok' => true,
            'marketplace' => $this->marketplacePayload(),
        ]);
    }

    public function buyerWalletTopUpStore(Request $request, Wallet $wallet): JsonResponse
    {
        abort_unless(Auth::check(), 401);
        abort_unless((int) $wallet->user_id === (int) Auth::id(), 403);
        abort_unless($wallet->wallet_type === WalletType::Buyer, 422, 'Top-up is only available for buyer wallets.');

        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_method' => ['required', 'string', 'max:32'],
            'payment_reference' => ['required', 'string', 'max:191'],
        ]);

        $result = app(WalletTopUpRequestService::class)->requestTopUp(new RequestWalletTopUpCommand(
            walletId: (int) $wallet->id,
            userId: (int) Auth::id(),
            amount: (string) $payload['amount'],
            paymentMethod: (string) $payload['payment_method'],
            paymentReference: trim((string) $payload['payment_reference']),
            idempotencyKey: 'web-buyer-top-up:'.$wallet->id.':'.(int) Auth::id().':'.(string) Str::uuid(),
        ));

        return response()->json([
            'ok' => true,
            'top_up_request' => $result,
            'marketplace' => $this->marketplacePayload(),
        ]);
    }

    public function sellerProductStore(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $this->validatedSellerProductPayload($request);
        $this->persistSellerProduct($seller, $payload);

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerProductUpdate(Request $request, Product $product): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        abort_unless((int) $product->seller_profile_id === (int) $seller->id, 403);

        $payload = $this->validatedSellerProductPayload($request);
        $this->persistSellerProduct($seller, $payload, $product);

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerProductDuplicate(Product $product): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        abort_unless((int) $product->seller_profile_id === (int) $seller->id, 403);

        DB::transaction(function () use ($product, $seller): void {
            $copy = $product->replicate(['uuid', 'published_at']);
            $copy->uuid = (string) Str::uuid();
            $copy->title = Str::limit('Copy of '.(string) $product->title, 255, '');
            $copy->status = 'draft';
            $copy->published_at = null;
            $copy->save();

            foreach ($product->inventoryRecords as $record) {
                InventoryRecord::query()->create([
                    'product_id' => $copy->id,
                    'product_variant_id' => null,
                    'stock_on_hand' => (int) $record->stock_on_hand,
                    'stock_reserved' => 0,
                    'stock_sold' => 0,
                    'version' => 1,
                ]);
            }

            foreach ($product->productVariants as $variant) {
                $newVariant = $variant->replicate(['uuid']);
                $newVariant->uuid = (string) Str::uuid();
                $newVariant->product_id = $copy->id;
                $newVariant->sku = $variant->sku ? $variant->sku.'-COPY-'.Str::upper(Str::random(4)) : 'VAR-'.$copy->id.'-'.Str::upper(Str::random(5));
                $newVariant->save();
                InventoryRecord::query()->create([
                    'product_id' => null,
                    'product_variant_id' => $newVariant->id,
                    'stock_on_hand' => (int) $variant->inventoryRecords->sum('stock_on_hand'),
                    'stock_reserved' => 0,
                    'stock_sold' => 0,
                    'version' => 1,
                ]);
            }

            $this->recordStockMovement($seller, $copy, null, (int) $copy->inventoryRecords()->sum('stock_on_hand'), 'duplicate', 'Product duplicated');
        });

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerProductBulk(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
            'action' => ['required', Rule::in(['draft', 'pending_review', 'published', 'out_of_stock', 'delete'])],
        ]);

        Product::query()
            ->where('seller_profile_id', $seller->id)
            ->whereIn('id', array_map('intval', $payload['ids']))
            ->get()
            ->each(function (Product $product) use ($payload): void {
                if ($payload['action'] === 'delete') {
                    $product->status = 'archived';
                    $product->save();
                    $product->delete();
                    return;
                }
                $product->status = (string) $payload['action'];
                $product->published_at = $payload['action'] === 'published' ? now() : null;
                $product->save();
            });

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerMediaUpload(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,pdf', 'max:10240'],
            'purpose' => ['nullable', Rule::in(['product_image', 'store_media', 'profile', 'payment_proof', 'support_attachment'])],
        ]);

        $file = $request->file('file');
        $purpose = (string) ($payload['purpose'] ?? 'product_image');
        $storagePurpose = in_array($purpose, ['payment_proof', 'support_attachment'], true) ? 'store_media' : $purpose;
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $safeName = (string) Str::uuid().'.'.(preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin');
        $relativeDir = sprintf('seller-uploads/%d/%s', (int) $seller->user_id, $storagePurpose);
        $relativePath = $relativeDir.'/'.$safeName;
        Storage::disk('local')->putFileAs($relativeDir, $file, $safeName);

        return response()->json([
            'ok' => true,
            'media' => [
                'path' => $relativePath,
                'url' => '/api/v1/media/'.str_replace('%2F', '/', rawurlencode($relativePath)),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'purpose' => $purpose,
            ],
        ]);
    }

    public function sellerKycDocumentUpload(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'doc_type' => ['required', Rule::in($this->kycDocumentTypes())],
        ]);
        $kyc = $this->activeSellerKyc($seller, true);
        abort_if(in_array((string) $kyc->status, ['submitted', 'under_review', 'third_party_pending', 'approved', 'verified'], true), 422, 'This KYC case cannot accept document changes now.');

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $safeName = (string) Str::uuid().'.'.(preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin');
        $relativeDir = sprintf('seller-uploads/%d/kyc/%d', (int) $seller->user_id, (int) $kyc->id);
        $relativePath = $relativeDir.'/'.$safeName;
        Storage::disk('local')->putFileAs($relativeDir, $file, $safeName);
        $absolute = Storage::disk('local')->path($relativePath);

        $document = KycDocument::query()->updateOrCreate(
            ['kyc_verification_id' => $kyc->id, 'doc_type' => (string) $payload['doc_type']],
            [
                'uuid' => (string) Str::uuid(),
                'storage_path' => $relativePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => (int) $file->getSize(),
                'checksum_sha256' => is_readable($absolute) ? hash_file('sha256', $absolute) : '',
                'status' => 'uploaded',
            ],
        );

        $this->kycProviderService->recordHistory($kyc, (string) $kyc->status, (string) $kyc->status, (int) $seller->user_id, 'document_uploaded', 'Seller uploaded '.$payload['doc_type']);

        return response()->json(['ok' => true, 'document' => $this->kycDocumentPayload($document), 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerKycDocumentPreview(Request $request, KycDocument $document)
    {
        $seller = $this->authenticatedSeller();
        $kyc = $document->kyc_verification;
        abort_unless($kyc instanceof KycVerification && (int) $kyc->seller_profile_id === (int) $seller->id, 403);

        $path = ltrim(str_replace('\\', '/', (string) $document->storage_path), '/');
        abort_if($path === '' || str_contains($path, '..') || ! Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => (string) ($document->mime_type ?: 'application/octet-stream'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function supportAttachmentPreview(ChatMessage $message)
    {
        abort_unless(Auth::check(), 401);

        $thread = $message->thread;
        abort_unless($thread instanceof ChatThread, 404);

        $actorId = (int) Auth::id();
        abort_unless(
            (int) ($thread->buyer_user_id ?? 0) === $actorId || (int) ($thread->seller_user_id ?? 0) === $actorId,
            403
        );

        $path = $this->mediaStoragePath((string) ($message->attachment_url ?? ''));
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => (string) ($message->attachment_mime ?: 'application/octet-stream'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function sellerKycSave(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $kyc = $this->activeSellerKyc($seller, true);
        abort_if(in_array((string) $kyc->status, ['submitted', 'under_review', 'third_party_pending', 'approved', 'verified'], true), 422, 'This KYC case cannot be edited now.');
        $payload = $this->validatedKycApplication($request);

        $before = (string) $kyc->status;
        $kyc->forceFill([
            'submitted_by_user_id' => $seller->user_id,
            'status' => 'draft',
            'personal_info_encrypted' => $payload['personal'] ?? [],
            'business_info_encrypted' => $payload['business'] ?? [],
            'bank_info_encrypted' => $payload['bank'] ?? [],
            'address_info_encrypted' => $payload['address'] ?? [],
        ])->save();
        $this->kycProviderService->recordHistory($kyc, $before, 'draft', (int) $seller->user_id, 'draft_saved', 'Seller saved KYC draft.');

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerKycSubmit(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $kyc = $this->activeSellerKyc($seller, true);
        abort_if(in_array((string) $kyc->status, ['submitted', 'under_review', 'third_party_pending', 'approved', 'verified'], true), 422, 'A KYC submission is already active.');
        $payload = $this->validatedKycApplication($request);
        $documents = KycDocument::query()->where('kyc_verification_id', $kyc->id)->pluck('doc_type')->all();
        $required = $this->requiredKycDocuments((string) data_get($payload, 'personal.identity_document_type', 'nid'));
        $missing = array_values(array_diff($required, $documents));
        if ($missing !== []) {
            return response()->json(['ok' => false, 'message' => 'Missing required KYC documents: '.implode(', ', $missing), 'missing' => $missing], 422);
        }

        DB::transaction(function () use ($seller, $kyc, $payload): void {
            $before = (string) $kyc->status;
            $session = $this->kycProviderService->createSession($kyc);
            $kyc->forceFill([
                'submitted_by_user_id' => $seller->user_id,
                'status' => 'third_party_pending',
                'personal_info_encrypted' => $payload['personal'] ?? [],
                'business_info_encrypted' => $payload['business'] ?? [],
                'bank_info_encrypted' => $payload['bank'] ?? [],
                'address_info_encrypted' => $payload['address'] ?? [],
                'submitted_at' => now(),
                'expires_at' => now()->addMonths((int) ($this->kycSettings()->expiry_months ?? 12)),
                'sla_due_at' => now()->addHours((int) config('admin_sla.kyc.breach_hours', 24)),
                'provider_session_id' => $session['session_id'],
                'provider_session_url' => $session['session_url'],
            ])->save();
            $seller->verification_status = 'pending';
            $seller->save();
            $this->kycProviderService->recordHistory($kyc, $before, 'third_party_pending', (int) $seller->user_id, 'submitted', 'Seller submitted KYC and provider session was created.');
            $this->notifyKycUser($seller, $kyc, 'seller.kyc.submitted', 'KYC submitted', 'Your KYC application was submitted. Complete the identity verification step to continue.');
        });

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function kycProviderWebhook(Request $request, string $provider): JsonResponse
    {
        $result = $this->kycProviderService->handleWebhook($provider, $request);
        if (! $result['ok']) {
            return response()->json($result, $result['status'] === 'invalid_signature' ? 401 : 422);
        }

        return response()->json($result);
    }

    public function inventoryAdjust(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'delta' => ['required', 'integer', 'min:-9999', 'max:9999'],
            'reason' => ['nullable', 'string', 'max:191'],
            'movement_type' => ['nullable', Rule::in(['stock_in', 'stock_out', 'transfer', 'adjustment'])],
        ]);

        $productId = (int) $payload['product_id'];
        $variantId = isset($payload['product_variant_id']) ? (int) $payload['product_variant_id'] : null;
        $delta = (int) $payload['delta'];

        $seller = $this->authenticatedSeller();
        if ($seller instanceof SellerProfile) {
            $product = Product::query()
                ->where('seller_profile_id', $seller->id)
                ->whereKey($productId)
                ->first();

            if ($product instanceof Product) {
                $record = InventoryRecord::query()->firstOrCreate(
                    $variantId !== null ? ['product_id' => null, 'product_variant_id' => $variantId] : ['product_id' => $product->id, 'product_variant_id' => null],
                    ['stock_on_hand' => 0, 'stock_reserved' => 0, 'stock_sold' => 0, 'version' => 1]
                );
                $record->stock_on_hand = max(0, (int) $record->stock_on_hand + $delta);
                $record->version = (int) $record->version + 1;
                $record->save();
                $this->recordStockMovement(
                    $seller,
                    $product,
                    $variantId,
                    $delta,
                    (string) ($payload['movement_type'] ?? ($delta >= 0 ? 'stock_in' : 'stock_out')),
                    (string) ($payload['reason'] ?? 'Manual stock adjustment'),
                    isset($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : null,
                    (int) $record->stock_on_hand,
                );
            }
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function warehouseStore(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        SellerWarehouse::query()->updateOrCreate(
            [
                'id' => $payload['id'] ?? null,
                'seller_profile_id' => $seller->id,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => (string) $payload['name'],
                'code' => $payload['code'] ?? null,
                'address' => $payload['address'] ?? null,
                'city' => $payload['city'] ?? null,
                'contact_person' => $payload['contact_person'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'status' => (string) ($payload['status'] ?? 'active'),
            ],
        );

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function warehouseDestroy(SellerWarehouse $sellerWarehouse): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        abort_unless((int) $sellerWarehouse->seller_profile_id === (int) $seller->id, 403);

        $sellerWarehouse->delete();

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function sellerShippingSettingsUpdate(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $request->validate([
            'cash_on_delivery_enabled' => ['nullable', 'boolean'],
            'processing_time_label' => ['required', 'string', 'max:80'],
            'shipping_methods' => ['required', 'array', 'min:1'],
            'shipping_methods.*.shipping_method_id' => ['nullable', 'integer'],
            'shipping_methods.*.method_name' => ['nullable', 'string', 'max:191'],
            'shipping_methods.*.price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'shipping_methods.*.processing_time_label' => ['required', 'string', 'max:80'],
            'shipping_methods.*.is_enabled' => ['nullable', 'boolean'],
            'shipping_methods.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $enabledCount = collect($payload['shipping_methods'])
            ->filter(static fn ($method): bool => (bool) ($method['is_enabled'] ?? false))
            ->count();

        if ($enabledCount < 1) {
            return response()->json(['ok' => false, 'message' => 'Enable at least one shipping method before saving.'], 422);
        }

        DB::transaction(function () use ($seller, $payload): void {
            $seller->forceFill([
                'cash_on_delivery_enabled' => (bool) ($payload['cash_on_delivery_enabled'] ?? false),
                'processing_time_label' => (string) $payload['processing_time_label'],
            ])->save();

            $seen = [];
            foreach ($payload['shipping_methods'] as $index => $method) {
                $shippingMethod = $this->shippingMethodFromPayload($method, $seller);

                if (! $shippingMethod instanceof ShippingMethod) {
                    continue;
                }
                $methodId = (int) $shippingMethod->id;
                $isEnabled = (bool) ($method['is_enabled'] ?? false);
                $seen[] = $methodId;

                SellerShippingMethod::query()->updateOrCreate(
                    [
                        'seller_profile_id' => $seller->id,
                        'shipping_method_id' => $methodId,
                    ],
                    [
                        'price' => (float) $method['price'],
                        'processing_time_label' => (string) $method['processing_time_label'],
                        'is_enabled' => $isEnabled,
                        'sort_order' => (int) ($method['sort_order'] ?? (($index + 1) * 10)),
                    ],
                );
            }

            SellerShippingMethod::query()
                ->where('seller_profile_id', $seller->id)
                ->when($seen !== [], static fn ($query) => $query->whereNotIn('shipping_method_id', $seen))
                ->update(['is_enabled' => false]);

            $enabledMethods = SellerShippingMethod::query()
                ->with('shippingMethod')
                ->where('seller_profile_id', $seller->id)
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->limit(2)
                ->get();

            $seller->forceFill([
                'inside_dhaka_label' => $enabledMethods->get(0)?->shippingMethod?->name,
                'inside_dhaka_fee' => $enabledMethods->get(0)?->price,
                'outside_dhaka_label' => $enabledMethods->get(1)?->shippingMethod?->name,
                'outside_dhaka_fee' => $enabledMethods->get(1)?->price,
            ])->save();
        });

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function payoutMethodStore(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $request->validate([
            'method_type' => ['required', Rule::in(['bkash', 'nagad', 'bank_transfer'])],
            'account_name' => ['required', 'string', 'max:191'],
            'account_number' => ['required', 'string', 'max:80'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'branch_name' => ['nullable', 'string', 'max:191'],
            'routing_number' => ['nullable', 'string', 'max:80'],
            'account_type_label' => ['nullable', 'string', 'max:80'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $methodType = (string) $payload['method_type'];
        if ($methodType === 'bank_transfer' && trim((string) ($payload['bank_name'] ?? '')) === '') {
            return response()->json(['ok' => false, 'message' => 'Bank name is required for bank transfer.'], 422);
        }

        DB::transaction(function () use ($seller, $payload, $methodType): void {
            $asDefault = (bool) ($payload['is_default'] ?? false);
            $hasExisting = PayoutAccount::query()->where('seller_profile_id', $seller->id)->exists();
            if ($asDefault || ! $hasExisting) {
                PayoutAccount::query()
                    ->where('seller_profile_id', $seller->id)
                    ->update(['is_default' => false]);
                $asDefault = true;
            }

            $bankName = trim((string) ($payload['bank_name'] ?? ''));
            PayoutAccount::query()->create([
                'seller_profile_id' => (int) $seller->id,
                'account_type' => $methodType === 'bank_transfer' ? 'bank' : 'mobile_money',
                'provider' => $methodType === 'bank_transfer' ? ($bankName !== '' ? $bankName : 'bank') : $methodType,
                'account_ref_token' => json_encode([
                    'method_type' => $methodType,
                    'account_name' => trim((string) $payload['account_name']),
                    'account_number' => trim((string) $payload['account_number']),
                    'bank_name' => $bankName !== '' ? $bankName : null,
                    'branch_name' => trim((string) ($payload['branch_name'] ?? '')) ?: null,
                    'routing_number' => trim((string) ($payload['routing_number'] ?? '')) ?: null,
                    'account_type_label' => trim((string) ($payload['account_type_label'] ?? '')) ?: null,
                ], JSON_THROW_ON_ERROR),
                'is_default' => $asDefault,
                'status' => 'active',
            ]);
        });

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function payoutMethodDestroy(PayoutAccount $payoutAccount): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        abort_unless((int) $payoutAccount->seller_profile_id === (int) $seller->id, 403);

        DB::transaction(function () use ($seller, $payoutAccount): void {
            $wasDefault = (bool) $payoutAccount->is_default;
            $payoutAccount->delete();

            if ($wasDefault) {
                $next = PayoutAccount::query()
                    ->where('seller_profile_id', $seller->id)
                    ->orderByDesc('id')
                    ->first();
                if ($next instanceof PayoutAccount) {
                    $next->is_default = true;
                    $next->save();
                }
            }
        });

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function topUpRequestStore(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string', 'max:80'],
            'payment_reference' => ['nullable', 'string', 'max:191'],
            'payment_proof_url' => ['nullable', 'string', 'max:2048'],
        ]);
        $wallet = $this->sellerWallet($seller);
        if (! $wallet instanceof Wallet) {
            return response()->json(['ok' => false, 'message' => 'No seller wallet is available for top-up requests.'], 422);
        }

        WalletTopUpRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'idempotency_key' => 'web-topup:'.$seller->id.':'.Str::uuid(),
            'wallet_id' => $wallet->id,
            'requested_by_user_id' => $seller->user_id,
            'status' => 'requested',
            'requested_amount' => number_format((float) $payload['amount'], 4, '.', ''),
            'payment_method' => (string) $payload['payment_method'],
            'payment_reference' => trim((string) ($payload['payment_reference'] ?? '')),
            'payment_proof_url' => $payload['payment_proof_url'] ?? null,
            'currency' => (string) ($wallet->currency ?? $seller->default_currency ?? 'BDT'),
        ]);

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function couponStore(Request $request): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        if (! $seller instanceof SellerProfile) {
            return response()->json(['ok' => false, 'message' => 'Sign in as a seller to manage offers.'], 403);
        }

        $promotion = new Promotion();
        $promotion->uuid = (string) Str::uuid();
        $promotion->created_by_user_id = (int) Auth::id();
        $promotion->used_count = 0;

        $this->fillSellerCouponPromotion($promotion, $request, $seller);
        $promotion->save();

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function couponUpdate(Request $request, Promotion $promotion): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        if (! $seller instanceof SellerProfile) {
            return response()->json(['ok' => false, 'message' => 'Sign in as a seller to manage offers.'], 403);
        }

        $promotion = $this->sellerCouponPromotionOrAbort($promotion, $seller);
        $this->fillSellerCouponPromotion($promotion, $request, $seller, true);
        $promotion->save();

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function couponToggle(Promotion $promotion): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        if (! $seller instanceof SellerProfile) {
            return response()->json(['ok' => false, 'message' => 'Sign in as a seller to manage offers.'], 403);
        }

        $promotion = $this->sellerCouponPromotionOrAbort($promotion, $seller);
        $promotion->is_active = ! $promotion->is_active;
        $promotion->save();

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function couponDestroy(Promotion $promotion): JsonResponse
    {
        $seller = $this->authenticatedSeller();
        if (! $seller instanceof SellerProfile) {
            return response()->json(['ok' => false, 'message' => 'Sign in as a seller to manage offers.'], 403);
        }

        $promotion = $this->sellerCouponPromotionOrAbort($promotion, $seller);
        $promotion->delete();

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function payoutRequestStore(Request $request): JsonResponse
    {
        $payload = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);
        $seller = $this->authenticatedSeller();

        if (! $seller instanceof SellerProfile) {
            return response()->json(['ok' => false, 'message' => 'Sign in as a seller to request payouts.'], 403);
        }

        if ($this->kycSettings()->require_for_withdrawal && ! $this->sellerKycVerified($seller)) {
            return response()->json(['ok' => false, 'message' => 'KYC verification is required before requesting a withdrawal.'], 403);
        }

        $wallet = Wallet::query()
            ->where('user_id', $seller->user_id)
            ->where('wallet_type', WalletType::Seller->value)
            ->where('currency', (string) ($seller->default_currency ?? 'BDT'))
            ->where('status', WalletAccountStatus::Active->value)
            ->first();

        if (! $wallet instanceof Wallet) {
            return response()->json(['ok' => false, 'message' => 'No active seller wallet is available for this currency yet.'], 422);
        }

        try {
            $this->withdrawalService->requestWithdrawal(new RequestWithdrawalCommand(
                sellerProfileId: (int) $seller->id,
                walletId: (int) $wallet->id,
                amount: number_format((float) $payload['amount'], 4, '.', ''),
                currency: (string) ($wallet->currency ?? $seller->default_currency ?? 'BDT'),
                idempotencyKey: 'web-payout:'.Auth::id().':'.Str::uuid(),
                feeAmount: '0.0000',
            ));
        } catch (WithdrawalValidationFailedException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage() ?: 'Unable to request payout. Please check your available seller balance.',
            ], 422);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function supportMessageStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'thread_id' => ['nullable', 'integer', 'min:1'],
            'ticket' => ['nullable', 'string', 'max:32'],
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment_url' => ['nullable', 'string', 'max:512'],
            'attachment_name' => ['nullable', 'string', 'max:191'],
            'attachment_type' => ['nullable', 'string', 'max:32'],
            'attachment_mime' => ['nullable', 'string', 'max:191'],
            'attachment_size' => ['nullable', 'integer', 'min:0', 'max:10240'],
        ]);
        $body = trim((string) ($payload['body'] ?? ''));
        $hasAttachment = trim((string) ($payload['attachment_url'] ?? '')) !== '';
        if ($body === '' && ! $hasAttachment) {
            return response()->json(['ok' => false, 'message' => 'Write a message or attach a file first.'], 422);
        }

        if (Auth::check()) {
            $actorId = (int) Auth::id();
            $isSeller = Auth::user()?->sellerProfile !== null;
            $thread = null;
            $resolvedThreadId = (int) ($payload['thread_id'] ?? 0);
            if ($resolvedThreadId <= 0) {
                $ticketCode = strtoupper((string) ($payload['ticket'] ?? ''));
                if (preg_match('/^SUP-(\d+)$/', $ticketCode, $matches) === 1) {
                    $resolvedThreadId = (int) ($matches[1] ?? 0);
                }
            }

            if ($resolvedThreadId > 0) {
                $thread = ChatThread::query()
                    ->whereKey($resolvedThreadId)
                    ->where(function ($query) use ($actorId): void {
                        $query->where('buyer_user_id', $actorId)
                            ->orWhere('seller_user_id', $actorId);
                    })
                    ->first();

                if (! $thread instanceof ChatThread) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'The selected support ticket could not be found. Please reload the page and try again.',
                    ], 404);
                }
            }

            if (! $thread instanceof ChatThread) {
                $thread = ChatThread::query()->firstOrCreate(
                    [
                        'kind' => 'support',
                        'buyer_user_id' => $actorId,
                        'seller_user_id' => $isSeller ? $actorId : null,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'subject' => $isSeller ? 'Seller support conversation' : 'Buyer support conversation',
                        'status' => 'open',
                        'last_message_at' => now(),
                    ]
                );
            }
            $senderRole = (int) ($thread->seller_user_id ?? 0) === $actorId ? 'seller' : 'buyer';
            $receiverUserId = (int) $thread->buyer_user_id === $actorId
                ? ($thread->seller_user_id !== null ? (int) $thread->seller_user_id : null)
                : (int) $thread->buyer_user_id;
            ChatMessage::query()->create([
                'uuid' => (string) Str::uuid(),
                'thread_id' => $thread->id,
                'sender_user_id' => $actorId,
                'receiver_user_id' => $receiverUserId,
                'sender_role' => $senderRole,
                'body' => $body,
                'attachment_url' => $payload['attachment_url'] ?? null,
                'attachment_name' => $payload['attachment_name'] ?? null,
                'attachment_type' => $payload['attachment_type'] ?? null,
                'attachment_mime' => $payload['attachment_mime'] ?? null,
                'attachment_size' => $payload['attachment_size'] ?? null,
            ]);
            $thread->last_message_at = now();
            $thread->save();
        } else {
            $messages = $request->session()->get('web_support_messages', []);
            $messages[] = [
                'from' => 'buyer',
                'body' => $body,
                'time' => now()->format('H:i'),
                'attachmentUrl' => $payload['attachment_url'] ?? null,
                'attachmentName' => $payload['attachment_name'] ?? null,
                'attachmentType' => $payload['attachment_type'] ?? null,
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
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (Auth::check()) {
            Auth::user()->fill([
                'display_name' => $payload['name'] ?? Auth::user()->display_name,
                'email' => $payload['email'] ?? Auth::user()->email,
                'phone' => $payload['phone'] ?? Auth::user()->phone,
                'avatar_url' => $payload['avatar_url'] ?? Auth::user()->avatar_url,
            ])->save();
            $this->writeBuyerAudit('buyer.profile.updated', 'profile_update');
        } else {
            $request->session()->put('web_profile', [
                'name' => $payload['name'] ?? 'Guest buyer',
                'email' => $payload['email'] ?? '',
                'phone' => $payload['phone'] ?? '',
                'city' => $payload['city'] ?? '',
                'avatar_url' => $payload['avatar_url'] ?? '',
            ]);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function profilePasswordUpdate(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in to update your password.'], 403);
        }

        $payload = $request->validate([
            'current_password' => ['required', 'string', 'max:1024'],
            'new_password' => ['required', 'string', 'min:8', 'max:1024'],
            'new_password_confirmation' => ['required', 'same:new_password'],
        ]);

        $user = Auth::user();
        if (! $user || ! password_verify((string) $payload['current_password'], (string) $user->password_hash)) {
            return response()->json(['ok' => false, 'message' => 'Your current password is incorrect.'], 422);
        }

        $user->password_hash = password_hash((string) $payload['new_password'], PASSWORD_DEFAULT);
        $user->save();
        $this->writeBuyerAudit('buyer.password.updated', 'password_change');

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function buyerProfilePhotoUpload(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in to update your profile photo.'], 403);
        }

        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $safeName = (string) Str::uuid().'.'.(preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin');
        $relativeDir = sprintf('buyer-uploads/%d/profile', (int) Auth::id());
        $relativePath = $relativeDir.'/'.$safeName;
        Storage::disk('local')->putFileAs($relativeDir, $file, $safeName);

        $user = Auth::user();
        $user?->forceFill(['avatar_url' => $relativePath])->save();
        $this->writeBuyerAudit('buyer.profile.photo_updated', 'profile_photo_change');

        return response()->json([
            'ok' => true,
            'media' => [
                'path' => $relativePath,
                'url' => '/api/v1/media/'.str_replace('%2F', '/', rawurlencode($relativePath)),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'purpose' => 'profile',
            ],
            'marketplace' => $this->marketplacePayload(),
        ]);
    }

    public function buyerNotificationPreferencesUpdate(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in to manage notification preferences.'], 403);
        }

        $payload = $request->validate([
            'in_app_enabled' => ['nullable', 'boolean'],
            'email_enabled' => ['nullable', 'boolean'],
            'order_updates_enabled' => ['nullable', 'boolean'],
            'promotion_enabled' => ['nullable', 'boolean'],
        ]);

        $preferences = UserNotificationPreference::query()->firstOrNew(['user_id' => (int) Auth::id()]);
        $preferences->fill([
            'in_app_enabled' => (bool) ($payload['in_app_enabled'] ?? $preferences->in_app_enabled ?? true),
            'email_enabled' => (bool) ($payload['email_enabled'] ?? $preferences->email_enabled ?? true),
            'order_updates_enabled' => (bool) ($payload['order_updates_enabled'] ?? $preferences->order_updates_enabled ?? true),
            'promotion_enabled' => (bool) ($payload['promotion_enabled'] ?? $preferences->promotion_enabled ?? true),
        ]);
        $preferences->save();
        $this->writeBuyerAudit('buyer.notifications.updated', 'notification_preferences_updated');

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function buyerAddressStore(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'Sign in to manage addresses.'], 403);
        }

        $address = new UserAddress();
        $address->user_id = (int) Auth::id();
        $this->fillBuyerAddress($address, $request);
        $address->save();

        if ($address->is_default) {
            UserAddress::query()
                ->where('user_id', (int) Auth::id())
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }
        $this->writeBuyerAudit('buyer.address.created', 'address_created', 'user_address', (int) $address->id);

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function buyerAddressUpdate(Request $request, UserAddress $address): JsonResponse
    {
        if (! Auth::check() || (int) $address->user_id !== (int) Auth::id()) {
            return response()->json(['ok' => false, 'message' => 'You cannot update this address.'], 403);
        }

        $this->fillBuyerAddress($address, $request);
        $address->save();

        if ($address->is_default) {
            UserAddress::query()
                ->where('user_id', (int) Auth::id())
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }
        $this->writeBuyerAudit('buyer.address.updated', 'address_updated', 'user_address', (int) $address->id);

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function buyerAddressDestroy(UserAddress $address): JsonResponse
    {
        if (! Auth::check() || (int) $address->user_id !== (int) Auth::id()) {
            return response()->json(['ok' => false, 'message' => 'You cannot remove this address.'], 403);
        }

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $replacement = UserAddress::query()
                ->where('user_id', (int) Auth::id())
                ->latest('id')
                ->first();
            if ($replacement instanceof UserAddress) {
                $replacement->is_default = true;
                $replacement->save();
            }
        }
        $this->writeBuyerAudit('buyer.address.deleted', 'address_deleted', 'user_address', (int) $address->id);

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    public function businessUpdate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'store_description' => ['nullable', 'string', 'max:255'],
            'store_logo_url' => ['nullable', 'string', 'max:2048'],
            'banner_image_url' => ['nullable', 'string', 'max:2048'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:120'],
            'verification' => ['nullable', 'string', 'max:80'],
        ]);

        $seller = Auth::user()?->sellerProfile;
        if ($seller instanceof SellerProfile) {
            $seller->fill([
                'display_name' => $payload['name'] ?? $seller->display_name,
                'legal_name' => $payload['store_description'] ?? $seller->legal_name,
                'store_logo_url' => $payload['store_logo_url'] ?? $seller->store_logo_url,
                'banner_image_url' => $payload['banner_image_url'] ?? $seller->banner_image_url,
                'contact_email' => $payload['contact_email'] ?? $seller->contact_email,
                'contact_phone' => $payload['phone'] ?? $seller->contact_phone,
                'address_line' => $payload['address_line'] ?? $payload['address'] ?? $seller->address_line,
                'city' => $payload['city'] ?? $seller->city,
                'region' => $payload['region'] ?? $seller->region,
                'postal_code' => $payload['postal_code'] ?? $seller->postal_code,
                'country' => $payload['country'] ?? $seller->country,
            ])->save();
        } else {
            $request->session()->put('web_business', $payload);
        }

        return response()->json(['ok' => true, 'marketplace' => $this->marketplacePayload()]);
    }

    /**
     * @return array{kind: string, label: string, subtitle: string, details: array<string, mixed>, is_default: bool}
     */
    private function validatedBuyerPaymentMethodPayload(Request $request): array
    {
        $payload = $request->validate([
            'kind' => ['required', Rule::in(['card', 'bkash', 'nagad', 'bank'])],
            'label' => ['required', 'string', 'max:191'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        return [
            'kind' => (string) $payload['kind'],
            'label' => trim((string) $payload['label']),
            'subtitle' => trim((string) ($payload['subtitle'] ?? '')),
            'details' => is_array($payload['details'] ?? null) ? $payload['details'] : [],
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSellerProductPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'sku' => ['nullable', 'string', 'max:128'],
            'brand' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'subcategory_id' => ['nullable', 'integer', 'min:1'],
            'product_type' => ['nullable', Rule::in(['physical', 'digital'])],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['nullable', 'string', 'max:2048'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'video_url' => ['nullable', 'string', 'max:2048'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock_alert' => ['nullable', 'integer', 'min:0'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'variants' => ['nullable', 'array'],
            'variants.*.title' => ['nullable', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:128'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.size' => ['nullable', 'string', 'max:80'],
            'variants.*.color' => ['nullable', 'string', 'max:80'],
            'variants.*.weight' => ['nullable', 'string', 'max:80'],
            'variants.*.attributes' => ['nullable', 'string', 'max:1000'],
            'shipping_weight' => ['nullable', 'string', 'max:80'],
            'shipping_dimensions' => ['nullable', 'string', 'max:120'],
            'tax_class' => ['nullable', 'string', 'max:120'],
            'warranty_information' => ['nullable', 'string', 'max:1000'],
            'return_policy' => ['nullable', 'string', 'max:2000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'out_of_stock'])],
            'condition' => ['nullable', 'string', 'max:64'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
            'digital_product_kind' => ['nullable', 'string', 'max:120'],
            'access_type' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:120'],
            'license_type' => ['nullable', 'string', 'max:120'],
            'delivery_fulfillment_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_instant_delivery' => ['nullable', 'boolean'],
            'is_service_product' => ['nullable', 'boolean'],
            'instant_delivery_expiration_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'digital_access_validity_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function persistSellerProduct(SellerProfile $seller, array $payload, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($seller, $payload, $product): Product {
            $categoryId = (int) ($payload['subcategory_id'] ?? $payload['category_id'] ?? $this->fallbackCategoryId());
            $status = (string) ($payload['status'] ?? 'pending_review');
            if ($status === 'published' && $this->kycSettings()->require_for_product_publish && ! $this->sellerKycVerified($seller)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'KYC verification is required before publishing products.',
                ]);
            }
            $basePrice = (float) $payload['price'];
            $salePrice = isset($payload['sale_price']) && $payload['sale_price'] !== '' ? (float) $payload['sale_price'] : null;
            $discountType = (string) ($payload['discount_type'] ?? 'percentage');
            $discountValue = isset($payload['discount_value']) && $payload['discount_value'] !== '' ? (float) $payload['discount_value'] : null;
            $discountPercentage = 0.0;
            $requestedProductType = (string) ($payload['product_type'] ?? 'physical');
            $isServiceProduct = $requestedProductType === 'digital' && filter_var($payload['is_service_product'] ?? false, FILTER_VALIDATE_BOOL);
            $isInstantDelivery = $requestedProductType === 'digital' && ! $isServiceProduct && filter_var($payload['is_instant_delivery'] ?? false, FILTER_VALIDATE_BOOL);
            $productType = match (true) {
                $isServiceProduct => 'service',
                $isInstantDelivery => 'digital',
                $requestedProductType === 'digital' => 'physical',
                default => 'physical',
            };
            $deliveryFulfillmentHours = isset($payload['delivery_fulfillment_hours']) && $payload['delivery_fulfillment_hours'] !== ''
                ? max(1, min(8760, (int) $payload['delivery_fulfillment_hours']))
                : ($isInstantDelivery ? 1 : ($isServiceProduct ? 72 : null));
            if ($salePrice !== null && $salePrice < $basePrice && $basePrice > 0) {
                $discountPercentage = round((($basePrice - $salePrice) / $basePrice) * 100, 2);
            } elseif ($discountValue !== null) {
                $discountPercentage = $discountType === 'fixed' && $basePrice > 0
                    ? round(($discountValue / $basePrice) * 100, 2)
                    : $discountValue;
            }
            $images = array_values(array_filter(array_unique([
                (string) ($payload['featured_image'] ?? ''),
                (string) ($payload['image_url'] ?? ''),
                ...array_map('strval', (array) ($payload['gallery_images'] ?? [])),
            ])));
            $attributes = array_filter([
                'slug' => $payload['slug'] ?? Str::slug((string) $payload['title']),
                'sku' => $payload['sku'] ?? null,
                'brand' => $payload['brand'] ?? null,
                'short_description' => $payload['short_description'] ?? null,
                'featured_image' => $images[0] ?? null,
                'video_url' => $payload['video_url'] ?? null,
                'sale_price' => $salePrice,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'low_stock_alert' => $payload['low_stock_alert'] ?? 5,
                'warehouse_id' => $payload['warehouse_id'] ?? null,
                'shipping_weight' => $payload['shipping_weight'] ?? null,
                'shipping_dimensions' => $payload['shipping_dimensions'] ?? null,
                'tax_class' => $payload['tax_class'] ?? null,
                'warranty_status' => $payload['warranty_information'] ?? null,
                'return_policy' => $payload['return_policy'] ?? null,
                'seo_title' => $payload['seo_title'] ?? null,
                'seo_description' => $payload['seo_description'] ?? null,
                'condition' => $payload['condition'] ?? 'New',
                'delivery_note' => $payload['delivery_note'] ?? null,
                'digital_product_kind' => $payload['digital_product_kind'] ?? null,
                'access_type' => $payload['access_type'] ?? null,
                'platform' => $payload['platform'] ?? null,
                'license_type' => $payload['license_type'] ?? null,
                'delivery_fulfillment_hours' => $deliveryFulfillmentHours,
                'is_instant_delivery' => $isInstantDelivery,
                'is_service_product' => $isServiceProduct,
                'requested_product_type' => $requestedProductType,
                'instant_delivery_expiration_hours' => $isInstantDelivery ? ($payload['instant_delivery_expiration_hours'] ?? null) : null,
                'digital_access_validity_hours' => $payload['digital_access_validity_hours'] ?? null,
                'tags' => array_values(array_filter(['web', 'seller', $productType, $isInstantDelivery ? 'instant_delivery' : null, $isServiceProduct ? 'service' : null])),
            ], static fn ($value): bool => $value !== null && $value !== '');

            $storefront = $this->ensureSellerStorefront($seller);
            $product ??= new Product(['uuid' => (string) Str::uuid(), 'seller_profile_id' => $seller->id]);
            $product->fill([
                'storefront_id' => (int) $storefront->id,
                'category_id' => $categoryId,
                'product_type' => $productType,
                'title' => (string) $payload['title'],
                'description' => $payload['description'] ?? null,
                'base_price' => number_format($basePrice, 4, '.', ''),
                'discount_percentage' => number_format(max(0, min(95, $discountPercentage)), 2, '.', ''),
                'discount_label' => $discountPercentage > 0 ? rtrim(rtrim(number_format($discountPercentage, 2), '0'), '.').'% OFF' : null,
                'currency' => (string) ($seller->default_currency ?? 'BDT'),
                'image_url' => $images[0] ?? null,
                'images_json' => $images !== [] ? $images : null,
                'attributes_json' => $attributes,
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
            ])->save();

            $targetStock = (int) ($payload['stock'] ?? 0);
            $record = InventoryRecord::query()->firstOrCreate(
                ['product_id' => $product->id, 'product_variant_id' => null],
                ['stock_on_hand' => 0, 'stock_reserved' => 0, 'stock_sold' => 0, 'version' => 1],
            );
            $delta = $targetStock - (int) $record->stock_on_hand;
            $record->stock_on_hand = $targetStock;
            $record->version = (int) $record->version + 1;
            $record->save();
            if ($delta !== 0) {
                $this->recordStockMovement($seller, $product, null, $delta, $delta > 0 ? 'stock_in' : 'stock_out', 'Product form stock sync', isset($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : null, $targetStock);
            }

            $this->syncProductVariants($product, (array) ($payload['variants'] ?? []));

            return $product->fresh();
        });
    }

    /**
     * @param list<array<string, mixed>> $variants
     */
    private function syncProductVariants(Product $product, array $variants): void
    {
        $existingVariantIds = $product->productVariants()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($existingVariantIds !== []) {
            ProductVariant::query()->whereIn('id', $existingVariantIds)->update(['is_active' => false]);
        }
        foreach ($variants as $index => $variantPayload) {
            $title = trim((string) ($variantPayload['title'] ?? ''));
            if ($title === '' && trim((string) ($variantPayload['sku'] ?? '')) === '') {
                continue;
            }
            $price = (float) ($variantPayload['price'] ?? $product->base_price);
            $variant = ProductVariant::query()->create([
                'uuid' => (string) Str::uuid(),
                'product_id' => $product->id,
                'sku' => (string) ($variantPayload['sku'] ?? ('SKU-'.$product->id.'-'.($index + 1))),
                'title' => $title !== '' ? $title : 'Variant '.($index + 1),
                'price_delta' => number_format($price - (float) $product->base_price, 4, '.', ''),
                'attributes_json' => array_filter([
                    'size' => $variantPayload['size'] ?? null,
                    'color' => $variantPayload['color'] ?? null,
                    'weight' => $variantPayload['weight'] ?? null,
                    'custom' => $variantPayload['attributes'] ?? null,
                ]),
                'is_active' => true,
            ]);
            InventoryRecord::query()->create([
                'product_id' => null,
                'product_variant_id' => $variant->id,
                'stock_on_hand' => (int) ($variantPayload['stock'] ?? 0),
                'stock_reserved' => 0,
                'stock_sold' => 0,
                'version' => 1,
            ]);
        }
    }

    private function recordStockMovement(SellerProfile $seller, Product $product, ?int $variantId, int $delta, string $type, string $reason, ?int $warehouseId = null, ?int $stockAfter = null): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }
        StockMovement::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'seller_warehouse_id' => $warehouseId,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'movement_type' => $type,
            'quantity_delta' => $delta,
            'stock_after' => max(0, (int) ($stockAfter ?? $product->inventoryRecords()->sum('stock_on_hand'))),
            'reason' => $reason,
            'reference' => 'WEB-'.$product->id.'-'.now()->format('His'),
            'created_by_user_id' => Auth::id(),
        ]);
    }

    private function sellerWallet(SellerProfile $seller): ?Wallet
    {
        return Wallet::query()
            ->where('user_id', $seller->user_id)
            ->where('wallet_type', WalletType::Seller->value)
            ->where('currency', (string) ($seller->default_currency ?? 'BDT'))
            ->where('status', WalletAccountStatus::Active->value)
            ->first();
    }

    private function activeSellerKyc(SellerProfile $seller, bool $create): ?KycVerification
    {
        $kyc = KycVerification::query()
            ->where('seller_profile_id', $seller->id)
            ->whereNotIn('status', ['expired'])
            ->latest('id')
            ->first();

        if ($kyc instanceof KycVerification || ! $create) {
            return $kyc;
        }

        $kyc = KycVerification::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'submitted_by_user_id' => $seller->user_id,
            'status' => 'draft',
            'provider_ref' => 'seller-kyc-'.$seller->id.'-'.Str::lower(Str::random(8)),
            'submitted_at' => null,
        ]);
        $this->kycProviderService->recordHistory($kyc, null, 'draft', (int) $seller->user_id, 'draft_created', 'Seller started KYC draft.');

        return $kyc;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedKycApplication(Request $request): array
    {
        return $request->validate([
            'personal' => ['required', 'array'],
            'personal.full_name' => ['required', 'string', 'max:191'],
            'personal.date_of_birth' => ['nullable', 'date'],
            'personal.nationality' => ['nullable', 'string', 'max:120'],
            'personal.id_number' => ['nullable', 'string', 'max:120'],
            'personal.identity_document_type' => ['nullable', Rule::in(['nid', 'driving_license', 'passport'])],
            'personal.phone' => ['nullable', 'string', 'max:80'],
            'business' => ['required', 'array'],
            'business.legal_name' => ['required', 'string', 'max:191'],
            'business.registration_number' => ['nullable', 'string', 'max:120'],
            'business.business_type' => ['nullable', 'string', 'max:120'],
            'business.tax_vat_number' => ['nullable', 'string', 'max:120'],
            'business.website' => ['nullable', 'string', 'max:191'],
            'bank' => ['required', 'array'],
            'bank.bank_name' => ['required', 'string', 'max:191'],
            'bank.account_name' => ['required', 'string', 'max:191'],
            'bank.account_number' => ['required', 'string', 'max:120'],
            'bank.routing_number' => ['nullable', 'string', 'max:120'],
            'bank.mobile_banking_provider' => ['nullable', 'string', 'max:80'],
            'bank.mobile_banking_number' => ['nullable', 'string', 'max:80'],
            'address' => ['required', 'array'],
            'address.address_line' => ['required', 'string', 'max:500'],
            'address.city' => ['required', 'string', 'max:120'],
            'address.region' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:40'],
            'address.country' => ['required', 'string', 'max:120'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function kycDocumentTypes(): array
    {
        return [
            'id_front', 'id_back', 'selfie',
            'nid_front', 'nid_back', 'nid_selfie',
            'license_front', 'license_back', 'license_selfie',
            'passport_page', 'passport_selfie',
            'business_license', 'address_proof', 'trade_license', 'tax_vat',
            'bank_account_proof', 'address_verification', 'face_verification', 'bank_statement',
        ];
    }

    /**
     * @return list<string>
     */
    private function requiredKycDocuments(?string $identityType = null): array
    {
        $identity = match ($identityType) {
            'driving_license' => ['license_front', 'license_back', 'license_selfie'],
            'passport' => ['passport_page', 'passport_selfie'],
            default => ['nid_front', 'nid_back', 'nid_selfie'],
        };

        return array_values(array_unique([
            ...$identity,
            'trade_license',
            'tax_vat',
            'bank_account_proof',
            'address_verification',
        ]));
    }

    private function kycSettings(): KycSetting
    {
        return KycSetting::query()->firstOrCreate(
            ['seller_type' => 'default'],
            [
                'require_for_product_publish' => true,
                'require_for_withdrawal' => true,
                'required_documents_json' => ['identity_dynamic', 'trade_license', 'tax_vat', 'bank_account_proof', 'address_verification'],
                'expiry_months' => 12,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function kycDocumentPayload(KycDocument $document): array
    {
        return [
            'id' => (int) $document->id,
            'docType' => (string) $document->doc_type,
            'status' => (string) $document->status,
            'originalName' => (string) ($document->original_name ?? 'Document'),
            'mimeType' => (string) ($document->mime_type ?? ''),
            'fileSize' => (int) ($document->file_size ?? 0),
            'checksum' => (string) ($document->checksum_sha256 ?? ''),
            'previewUrl' => URL::temporarySignedRoute('web.actions.seller.kyc.documents.preview', now()->addMinutes(10), ['document' => $document->id]),
            'createdAt' => $document->created_at?->format('M j, Y H:i'),
        ];
    }

    private function notifyKycUser(SellerProfile $seller, KycVerification $kyc, string $template, string $title, string $body): void
    {
        Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => (int) $seller->user_id,
            'channel' => 'in_app',
            'template_code' => $template,
            'payload_json' => [
                'title' => $title,
                'body' => $body,
                'href' => '/seller/kyc',
                'kyc_id' => $kyc->id,
                'status' => $kyc->status,
            ],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => (int) $seller->user_id,
            'channel' => 'email',
            'template_code' => $template,
            'payload_json' => ['subject' => $title, 'body' => $body, 'kyc_id' => $kyc->id],
            'status' => 'queued',
        ]);
    }

    private function sellerKycVerified(SellerProfile $seller): bool
    {
        return in_array((string) $seller->verification_status, ['verified', 'approved'], true)
            || KycVerification::query()
                ->where('seller_profile_id', $seller->id)
                ->whereIn('status', ['approved', 'verified'])
                ->exists();
    }

    private function ensureSellerStorefront(SellerProfile $seller): Storefront
    {
        $storefront = $seller->storefront;
        if ($storefront instanceof Storefront) {
            return $storefront;
        }

        return Storefront::query()->firstOrCreate(
            ['seller_profile_id' => $seller->id],
            [
                'uuid' => (string) Str::uuid(),
                'slug' => Str::slug((string) ($seller->display_name ?? 'seller-'.$seller->id)).'-'.$seller->id,
                'title' => (string) ($seller->display_name ?? 'Seller Store'),
                'description' => 'Seller storefront',
                'policy_text' => null,
                'is_public' => true,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function walletPayload(SellerProfile $seller): array
    {
        $wallet = $this->sellerWallet($seller);
        if (! $wallet instanceof Wallet) {
            return [
                'currentBalance' => 0,
                'availableBalance' => 0,
                'pendingBalance' => 0,
                'totalSales' => 0,
                'commissionDeducted' => 0,
                'totalWithdrawals' => 0,
                'transactions' => [],
                'topUps' => [],
                'minimumWithdraw' => (float) (WithdrawalSetting::query()->value('minimum_withdrawal_amount') ?? 500),
            ];
        }

        $ledger = WalletLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->limit(30)
            ->get();
        $credits = (float) WalletLedgerEntry::query()->where('wallet_id', $wallet->id)->where('entry_side', 'credit')->sum('amount');
        $debits = (float) WalletLedgerEntry::query()->where('wallet_id', $wallet->id)->where('entry_side', 'debit')->sum('amount');
        $withdrawals = (float) WithdrawalRequest::query()->where('seller_profile_id', $seller->id)->sum('requested_amount');
        $pendingWithdrawals = (float) WithdrawalRequest::query()->where('seller_profile_id', $seller->id)->whereIn('status', ['requested', 'pending', 'processing', 'reviewing'])->sum('requested_amount');

        return [
            'currentBalance' => max(0, $credits - $debits),
            'availableBalance' => max(0, $credits - $debits - $pendingWithdrawals),
            'pendingBalance' => $pendingWithdrawals,
            'totalSales' => (float) Order::query()->where('seller_user_id', $seller->user_id)->sum('net_amount'),
            'commissionDeducted' => (float) Order::query()->where('seller_user_id', $seller->user_id)->sum('fee_amount'),
            'totalWithdrawals' => $withdrawals,
            'minimumWithdraw' => (float) (WithdrawalSetting::query()->value('minimum_withdrawal_amount') ?? 500),
            'transactions' => $ledger->map(static fn (WalletLedgerEntry $entry): array => [
                'id' => (int) $entry->id,
                'type' => $entry->entry_type instanceof \BackedEnum ? $entry->entry_type->value : (string) $entry->entry_type,
                'direction' => $entry->entry_side instanceof \BackedEnum ? $entry->entry_side->value : (string) $entry->entry_side,
                'amount' => (float) $entry->amount,
                'currency' => (string) $entry->currency,
                'reference' => (string) ($entry->reference_type ?? 'ledger'),
                'createdAt' => $entry->created_at?->format('M j, Y H:i'),
            ])->values()->all(),
            'topUps' => WalletTopUpRequest::query()
                ->where('wallet_id', $wallet->id)
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(static function (WalletTopUpRequest $request): array {
                    $status = $request->status instanceof \BackedEnum ? $request->status->value : (string) $request->status;
                    return [
                        'id' => 'TU-'.$request->id,
                        'amount' => (float) $request->requested_amount,
                        'status' => $status,
                        'method' => (string) ($request->payment_method ?? 'Manual payment'),
                        'reference' => (string) ($request->payment_reference ?? ''),
                        'proof' => (string) ($request->payment_proof_url ?? ''),
                        'createdAt' => $request->created_at?->format('M j, Y H:i'),
                    ];
                })
                ->values()
                ->all(),
        ];
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
            ->with([
                'seller_profile.user',
                'category.parent',
                'inventoryRecords',
                'productVariants.inventoryRecords',
                'reviews' => static fn ($query) => $query->where('status', 'visible')->with('buyer')->latest(),
            ])
            ->withCount([
                'reviews' => static fn ($query) => $query->where('status', 'visible'),
                'orderItems',
            ])
            ->withAvg([
                'reviews as reviews_avg_rating' => static fn ($query) => $query->where('status', 'visible'),
            ], 'rating')
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
                'isAuthenticated' => true,
                'name' => (string) ($user->display_name ?: 'User #'.(int) $user->id),
                'email' => (string) ($user->email ?? ''),
                'role' => $user->sellerProfile === null ? 'buyer' : 'seller',
                'roles' => array_values(array_filter([
                    'buyer',
                    $user->sellerProfile === null ? null : 'seller',
                ])),
                'hasSellerProfile' => $user->sellerProfile !== null,
                'buyerAccountId' => (int) $user->id,
                'sellerAccountId' => $user->sellerProfile?->id ? (int) $user->sellerProfile->id : null,
                'status' => (string) ($user->status ?? 'active'),
                'phone' => (string) ($user->phone ?? ''),
                'avatarUrl' => $this->imageUrl($user->avatar_url),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'city' => (string) ($user->sellerProfile?->city ?? ''),
            ],
            'products' => $products,
            'categories' => $categories,
            'cart' => $this->cartPayload(),
            'wishlist' => $this->wishlistPayload(),
            'orders' => $this->ordersPayload(),
            'checkoutContext' => $this->checkoutContextPayload(),
            'buyerOps' => $this->buyerOperationsPayload(),
            'sellerProducts' => $this->sellerProductsPayload(),
            'coupons' => $this->couponPayload(),
            'payoutRequests' => $this->payoutPayload(),
            'business' => $this->businessPayload(),
            'sellerOps' => $this->sellerOperationsPayload(),
            'chats' => $this->chatPayload(),
            'supportTickets' => $this->supportTicketPayload(),
            'featuredVendor' => $this->featuredVendorPayload(),
            'hero' => $this->heroPayload($products),
            'flashDeal' => $this->flashDealPayload($products),
            'trustItems' => $this->trustItemsPayload(),
            'metrics' => $this->metricsPayload($products),
        ];
    }

    private function checkoutContextPayload(): array
    {
        $items = collect($this->cartPayload())->values();
        $subtotal = round($items->sum(static fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1)), 2);
        $requiresShipping = $items->contains(static fn (array $item): bool => (string) ($item['productType'] ?? 'physical') === 'physical');
        $hasDigital = $items->contains(static fn (array $item): bool => (string) ($item['productType'] ?? '') === 'digital');
        $hasService = $items->contains(static fn (array $item): bool => (string) ($item['productType'] ?? '') === 'service');

        $shippingOptions = $requiresShipping
            ? collect(['standard', 'express'])->map(fn (string $code): array => [
                'code' => $code,
                'label' => $code === 'express' ? 'Express delivery' : 'Standard delivery',
                'fee' => number_format($this->promotionService->shippingFeeForMethod($code, true), 2, '.', ''),
                'processing_time' => $code === 'express' ? 'Priority dispatch' : '1-3 business days',
            ])->values()->all()
            : [[
                'code' => 'digital',
                'label' => $hasService ? 'Service coordination' : 'Digital delivery',
                'fee' => '0.00',
                'processing_time' => $hasService ? 'Seller will coordinate fulfillment in chat' : 'Delivered digitally after secure payment',
            ]];

        $selectedAddress = null;
        $selectedPaymentMethod = null;
        $walletAvailable = '0.00';
        $walletHeld = '0.00';
        if (Auth::check()) {
            $selectedAddress = UserAddress::query()
                ->where('user_id', (int) Auth::id())
                ->orderByDesc('is_default')
                ->latest('id')
                ->first();
            $selectedPaymentMethod = UserPaymentMethod::query()
                ->where('user_id', (int) Auth::id())
                ->orderByDesc('is_default')
                ->latest('id')
                ->first();

            $walletLedger = app(WalletLedgerService::class);
            $buyerWallet = Wallet::query()
                ->where('user_id', (int) Auth::id())
                ->where('wallet_type', WalletType::Buyer)
                ->orderBy('id')
                ->first();
            if ($buyerWallet instanceof Wallet) {
                $balances = $walletLedger->computeWalletBalances(new ComputeWalletBalancesCommand((int) $buyerWallet->id));
                $walletAvailable = number_format((float) ($balances['available_balance'] ?? 0), 2, '.', '');
                $walletHeld = number_format((float) ($balances['held_balance'] ?? 0), 2, '.', '');
            }
        }

        $defaultShippingCode = $shippingOptions[0]['code'] ?? 'standard';
        $shippingFee = (float) ($shippingOptions[0]['fee'] ?? 0);
        $total = round($subtotal + $shippingFee, 2);

        return [
            'items_count' => $items->count(),
            'requires_shipping' => $requiresShipping,
            'has_digital' => $hasDigital,
            'has_service' => $hasService,
            'currency' => (string) ($items->first()['currency'] ?? 'BDT'),
            'shipping_options' => $shippingOptions,
            'default_shipping_method' => $defaultShippingCode,
            'default_address_id' => $selectedAddress?->id,
            'default_payment_method_id' => $selectedPaymentMethod?->id,
            'wallet_available' => $walletAvailable,
            'wallet_held' => $walletHeld,
            'summary' => [
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'shipping_fee' => number_format($shippingFee, 2, '.', ''),
                'escrow_fee' => '0.00',
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => number_format($total, 2, '.', ''),
            ],
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
            $isInstantDelivery = $productType === 'instant_delivery'
            || filter_var($attributes['is_instant_delivery'] ?? false, FILTER_VALIDATE_BOOL)
            || in_array(strtolower((string) ($attributes['delivery_type'] ?? '')), ['instant', 'instant_delivery'], true);
        $isServiceProduct = $productType === 'service'
            || filter_var($attributes['is_service_product'] ?? false, FILTER_VALIDATE_BOOL)
            || in_array(strtolower((string) ($attributes['delivery_type'] ?? '')), ['service', 'manual_delivery'], true);
        $normalizedProductType = $productType === 'instant_delivery' ? 'digital' : $productType;
        $stockOnHand = (int) $product->inventoryRecords->sum('stock_on_hand');
        $stockReserved = (int) $product->inventoryRecords->sum('stock_reserved');
        $category = $product->category;
        $parentCategory = $category?->parent;
        $categoryName = (string) ($parentCategory?->name ?? $category?->name ?? 'Marketplace');
        $subcategoryName = $parentCategory !== null ? (string) ($category?->name ?? '') : '';
        $reviewCount = (int) ($product->reviews_count ?? 0);
        $averageRating = $product->getAttribute('reviews_avg_rating');
        if ($averageRating === null && $reviewCount > 0) {
            $averageRating = $product->reviews()->where('status', 'visible')->avg('rating');
        }
        $seller = $product->seller_profile;
        $includedItems = is_array($attributes['included_items'] ?? null)
            ? array_values(array_filter(array_map('strval', $attributes['included_items'])))
            : [
                'Escrow checkout protection',
                'Verified seller signals',
                $isInstantDelivery ? 'Automatic digital fulfillment' : 'Order chat support',
                $isInstantDelivery ? 'Instant delivery access' : 'Delivery tracking workflow',
            ];
        $coverItems = is_array($attributes['what_we_cover'] ?? null)
            ? array_values(array_filter(array_map('strval', $attributes['what_we_cover'])))
            : array_values(array_filter([
                ($attributes['return_policy'] ?? null) ? 'Return policy: '.$attributes['return_policy'] : 'Protected return and dispute workflow',
                ($attributes['warranty_status'] ?? null) ? 'Warranty: '.$attributes['warranty_status'] : 'Seller support after purchase',
                ($attributes['shipping_weight'] ?? null) ? 'Shipping weight: '.$attributes['shipping_weight'] : null,
                $isServiceProduct ? 'Service delivery milestone review' : null,
                $isInstantDelivery ? 'Secure instant access after checkout' : null,
            ]));
        $faqItems = collect(is_array($attributes['faqs'] ?? null) ? $attributes['faqs'] : [])
            ->map(static function ($item): array {
                if (is_array($item)) {
                    return [
                        'question' => (string) ($item['question'] ?? $item['title'] ?? ''),
                        'answer' => (string) ($item['answer'] ?? $item['body'] ?? ''),
                    ];
                }

                return ['question' => '', 'answer' => (string) $item];
            })
            ->filter(static fn (array $item): bool => $item['question'] !== '' || $item['answer'] !== '')
            ->values()
            ->all();
        if ($faqItems === []) {
            $faqItems = [
                [
                    'question' => 'How does protected checkout work?',
                    'answer' => 'Payment is handled through the marketplace checkout flow, with order status, chat, delivery, return, and dispute support available from the buyer dashboard.',
                ],
                [
                    'question' => 'Can I contact the seller before buying?',
                    'answer' => 'Yes. Use Chat Seller to ask about availability, fulfillment, warranty, delivery timing, or custom requirements before placing the order.',
                ],
                [
                    'question' => 'What happens after I buy?',
                    'answer' => $this->productTypeHint($normalizedProductType, $isInstantDelivery),
                ],
            ];
        }
        $reviews = $product->relationLoaded('reviews')
            ? $product->reviews
                ->map(fn ($review): array => [
                    'id' => (int) $review->id,
                    'rating' => (int) $review->rating,
                    'feedbackType' => (string) ($review->feedback_type ?? ((int) $review->rating >= 4 ? 'good' : ((int) $review->rating <= 2 ? 'bad' : 'neutral'))),
                    'comment' => (string) ($review->comment ?? ''),
                    'tags' => $review->tags ?? [],
                    'sellerReply' => (string) ($review->seller_reply ?? ''),
                    'helpfulCount' => (int) ($review->helpful_count ?? 0),
                    'buyer' => (string) ($review->buyer?->name ?? 'Verified buyer'),
                    'buyerProfileHref' => ((int) $review->buyer_user_id) > 0 ? '/profiles/buyers/'.(int) $review->buyer_user_id : null,
                    'sellerProfileId' => (int) $review->seller_profile_id,
                    'createdAt' => $review->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        return [
            'id' => (int) $product->id,
            'uuid' => (string) ($product->uuid ?? ''),
            'title' => (string) ($product->title ?? 'Untitled listing'),
            'slug' => (string) ($attributes['slug'] ?? Str::slug((string) $product->title)),
            'sku' => (string) ($attributes['sku'] ?? $product->uuid ?? 'SKU-'.$product->id),
            'category_id' => (int) ($parentCategory?->id ?? $product->category_id),
            'subcategory_id' => $parentCategory !== null ? (int) $product->category_id : null,
            'category' => $categoryName,
            'subcategory' => $subcategoryName,
            'type' => str_contains(strtolower($productType), 'classified') ? 'Classified' : 'Marketplace',
            'productType' => $normalizedProductType,
            'productTypeLabel' => $this->productTypeLabel($normalizedProductType, $isInstantDelivery),
            'fulfillmentHint' => $this->productTypeHint($normalizedProductType, $isInstantDelivery),
            'isInstantDelivery' => $isInstantDelivery,
            'isServiceProduct' => $isServiceProduct,
            'deliveryFulfillmentHours' => isset($attributes['delivery_fulfillment_hours']) ? (int) $attributes['delivery_fulfillment_hours'] : null,
            'price' => $price,
            'oldPrice' => $discountPercentage > 0 ? $basePrice : $price,
            'regularPrice' => $basePrice,
            'salePrice' => $attributes['sale_price'] ?? null,
            'discountType' => (string) ($attributes['discount_type'] ?? 'percentage'),
            'discountValue' => $attributes['discount_value'] ?? null,
            'discountPercentage' => $discountPercentage,
            'discountLabel' => $discountLabel,
            'activeCampaign' => $campaign,
            'stock' => $stockOnHand,
            'lowStockAlert' => (int) ($attributes['low_stock_alert'] ?? 5),
            'stockReserved' => $stockReserved,
            'stockSold' => (int) $product->inventoryRecords->sum('stock_sold'),
            'availableStock' => max(0, $stockOnHand - $stockReserved),
            'warehouseStocks' => [
                (string) (($attributes['warehouse'] ?? null) ?: 'Main Warehouse') => max(0, $stockOnHand - $stockReserved),
            ],
            'city' => (string) ($seller?->city ?? $attributes['product_location'] ?? ''),
            'seller' => (string) ($seller?->display_name ?? 'Verified seller'),
            'sellerProfileId' => $seller !== null ? (int) $seller->id : null,
            'sellerProfileHref' => $seller !== null ? '/profiles/sellers/'.(int) $seller->id : null,
            'sellerStatus' => (string) ($seller?->verification_status ?? ''),
            'storeStatus' => (string) ($seller?->store_status ?? ''),
            'sellerDetails' => [
                'id' => $seller !== null ? (int) $seller->id : null,
                'href' => $seller !== null ? '/profiles/sellers/'.(int) $seller->id : null,
                'name' => (string) ($seller?->display_name ?? 'Verified seller'),
                'legalName' => (string) ($seller?->legal_name ?? ''),
                'logo' => $this->imageUrl((string) ($seller?->store_logo_url ?? '')),
                'banner' => $this->imageUrl((string) ($seller?->banner_image_url ?? '')),
                'city' => (string) ($seller?->city ?? ''),
                'region' => (string) ($seller?->region ?? ''),
                'country' => (string) ($seller?->country ?? ''),
                'memberSince' => $seller?->created_at?->format('Y'),
                'verificationStatus' => (string) ($seller?->verification_status ?? ''),
                'storeStatus' => (string) ($seller?->store_status ?? ''),
                'processingTime' => (string) ($seller?->processing_time_label ?? ''),
                'description' => (string) ($attributes['seller_description'] ?? $seller?->legal_name ?? 'This seller is part of the Sellova marketplace and supports protected order communication, fulfillment updates, and post-purchase support.'),
            ],
            'rating' => $averageRating !== null ? round((float) $averageRating, 1) : null,
            'reviewCount' => $reviewCount,
            'salesCount' => (int) ($product->order_items_count ?? 0),
            'verified' => in_array((string) $product->seller_profile?->verification_status, ['verified', 'approved'], true),
            'condition' => (string) ($attributes['condition'] ?? 'New'),
            'image' => $image,
            'images' => $images,
            'featuredImage' => (string) ($attributes['featured_image'] ?? $image ?? ''),
            'videoUrl' => (string) ($attributes['video_url'] ?? ''),
            'attributes' => $attributes,
            'attributeRows' => $this->attributeRows($attributes),
            'includedItems' => $includedItems,
            'coverItems' => $coverItems,
            'faqs' => $faqItems,
            'reviews' => $reviews,
            'brand' => (string) ($attributes['brand'] ?? ''),
            'warrantyStatus' => (string) ($attributes['warranty_status'] ?? ''),
            'returnPolicy' => (string) ($attributes['return_policy'] ?? ''),
            'shippingWeight' => (string) ($attributes['shipping_weight'] ?? ''),
            'shippingDimensions' => (string) ($attributes['shipping_dimensions'] ?? ''),
            'taxClass' => (string) ($attributes['tax_class'] ?? ''),
            'seoTitle' => (string) ($attributes['seo_title'] ?? ''),
            'seoDescription' => (string) ($attributes['seo_description'] ?? ''),
            'productLocation' => (string) ($attributes['product_location'] ?? $product->seller_profile?->city ?? ''),
            'variants' => $product->productVariants
                ->map(static fn ($variant): array => [
                    'id' => (int) $variant->id,
                    'title' => (string) ($variant->title ?? 'Variant #'.$variant->id),
                    'sku' => (string) ($variant->sku ?? ''),
                    'attributes' => is_array($variant->attributes_json) ? $variant->attributes_json : [],
                    'price' => (float) $product->base_price + (float) $variant->price_delta,
                    'priceDelta' => (float) $variant->price_delta,
                    'active' => (bool) $variant->is_active,
                    'stock' => (int) $variant->inventoryRecords->sum('stock_on_hand'),
                ])
                ->values()
                ->all(),
            'tags' => is_array($attributes['tags'] ?? null) ? $attributes['tags'] : ['escrow'],
            'description' => (string) ($product->description ?? 'Verified marketplace listing.'),
            'shortDescription' => (string) ($attributes['short_description'] ?? Str::limit((string) $product->description, 160)),
            'status' => (string) $product->status,
            'publishedAt' => $product->published_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    private function flashDealPayload(array $products): array
    {
        $dealProducts = collect($products)
            ->filter(static fn (array $product): bool => is_array($product['activeCampaign'] ?? null))
            ->sortByDesc(static fn (array $product): float => (float) ($product['activeCampaign']['discount_percentage'] ?? $product['discountPercentage'] ?? 0))
            ->values();

        $campaigns = $dealProducts
            ->pluck('activeCampaign')
            ->filter(static fn ($campaign): bool => is_array($campaign))
            ->values();

        $endsAt = $campaigns
            ->pluck('ends_at')
            ->filter()
            ->sort()
            ->first();

        if ($endsAt === null) {
            $dailyEnd = $campaigns
                ->pluck('daily_end_time')
                ->filter()
                ->sort()
                ->first();

            if (is_string($dailyEnd) && $dailyEnd !== '') {
                $endsAt = now()->setTimeFromTimeString($dailyEnd)->toIso8601String();
            }
        }

        return [
            'title' => (string) ($campaigns->first()['title'] ?? 'Flash Deals'),
            'badge' => (string) ($campaigns->first()['badge'] ?? 'Limited time'),
            'active' => $dealProducts->isNotEmpty(),
            'serverTime' => now()->toIso8601String(),
            'endsAt' => $endsAt,
            'productIds' => $dealProducts->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all(),
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
            ->withCount([
                'reviews' => static fn ($query) => $query->where('status', 'visible'),
                'orderItems',
            ])
            ->withAvg([
                'reviews as reviews_avg_rating' => static fn ($query) => $query->where('status', 'visible'),
            ], 'rating')
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
                ->with(['primaryProduct', 'escrowAccount', 'paymentIntents', 'paymentTransactions'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(static function (Order $order): array {
                    $latestIntent = $order->paymentIntents->sortByDesc('id')->first();
                    $latestTxn = $order->paymentTransactions->sortByDesc('id')->first();
                    $status = $order->status instanceof \BackedEnum ? (string) $order->status->value : (string) $order->status;
                    $escrowState = $order->escrowAccount?->state?->value ?? null;
                    $paymentMethod = $latestTxn?->raw_payload_json['method']
                        ?? $latestTxn?->raw_payload_json['payment_method']
                        ?? $latestIntent?->provider;

                    return [
                        'id' => (string) ($order->order_number ?? 'SO-'.$order->id),
                        'product' => (string) ($order->primaryProduct?->title ?? $order->product_type ?? 'Marketplace order'),
                        'amount' => (float) $order->net_amount,
                        'status' => $status,
                        'payment_status' => $status,
                        'payment_method' => $paymentMethod,
                        'payment_provider' => $latestIntent?->provider,
                        'escrow_state' => $escrowState,
                        'stage' => $escrowState === 'held' ? 'Escrow funded' : (string) ($order->fulfillment_state ?? 'Order received'),
                        'eta' => $order->seller_deadline_at?->format('M j') ?? 'Pending',
                        'progress' => $order->completed_at !== null ? 100 : ($escrowState === 'held' ? 55 : 35),
                    ];
                })
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

    private function buyerOperationsPayload(): array
    {
        if (! Auth::check()) {
            return [
                'wallets' => [],
                'walletSummary' => null,
                'paymentMethods' => [],
                'notifications' => [],
                'devices' => [],
                'activity' => [],
                'recentlyViewed' => $this->recentlyViewedPayload(),
                'favoriteStores' => [],
                'reviews' => [],
                'returns' => [],
                'escrows' => [],
                'ordersDetailed' => [],
                'savedItems' => [],
            'security' => [
                'accountStatus' => 'guest',
                'lastLoginAt' => null,
                'twoFactorEnabled' => false,
            ],
            'notificationPreferences' => [
                'inAppEnabled' => true,
                'emailEnabled' => true,
                'orderUpdatesEnabled' => true,
                'promotionEnabled' => false,
                'securityAlertsEnabled' => true,
            ],
            'addresses' => [],
            ];
        }

        $user = Auth::user();
        $walletLedger = app(WalletLedgerService::class);
        $wallets = Wallet::query()
            ->where('user_id', (int) Auth::id())
            ->orderBy('wallet_type')
            ->orderBy('currency')
            ->get()
            ->map(function (Wallet $wallet) use ($walletLedger): array {
                $balances = $walletLedger->computeWalletBalances(new ComputeWalletBalancesCommand((int) $wallet->id));

                return [
                    'id' => (int) $wallet->id,
                    'type' => (string) $wallet->wallet_type->value,
                    'currency' => (string) $wallet->currency,
                    'status' => (string) $wallet->status->value,
                    'availableBalance' => (string) ($balances['available_balance'] ?? '0.0000'),
                    'heldBalance' => (string) ($balances['held_balance'] ?? '0.0000'),
                    'topUpAllowed' => (string) $wallet->wallet_type->value === WalletType::Buyer->value,
                    'recentTopUps' => $wallet->walletTopUpRequests()
                        ->latest('id')
                        ->limit(5)
                        ->get()
                        ->map(static fn (WalletTopUpRequest $request): array => [
                            'id' => (int) $request->id,
                            'amount' => (string) $request->requested_amount,
                            'currency' => (string) ($request->currency ?? $wallet->currency),
                            'status' => (string) $request->status->value,
                            'paymentMethod' => (string) ($request->payment_method ?? ''),
                            'paymentReference' => (string) ($request->payment_reference ?? ''),
                            'reviewedAt' => $request->reviewed_at?->toIso8601String(),
                            'createdAt' => $request->created_at?->toIso8601String(),
                        ])->values()->all(),
                    'recentEntries' => $wallet->walletLedgerEntries()
                        ->latest('id')
                        ->limit(10)
                        ->get()
                        ->map(static fn (WalletLedgerEntry $entry): array => [
                            'id' => (int) $entry->id,
                            'entryType' => (string) $entry->entry_type->value,
                            'entrySide' => (string) $entry->entry_side->value,
                            'amount' => (string) $entry->amount,
                            'currency' => (string) $entry->currency,
                            'description' => (string) ($entry->description ?? ''),
                            'createdAt' => $entry->created_at?->toIso8601String(),
                        ])->values()->all(),
                ];
            })
            ->values();
        $walletSummary = $wallets->reduce(static function (array $carry, array $wallet): array {
            $carry['available'] += (float) ($wallet['availableBalance'] ?? 0);
            $carry['held'] += (float) ($wallet['heldBalance'] ?? 0);
            $carry['topUps'] += count($wallet['recentTopUps'] ?? []);
            $carry['transactions'] += count($wallet['recentEntries'] ?? []);

            return $carry;
        }, ['available' => 0.0, 'held' => 0.0, 'topUps' => 0, 'transactions' => 0]);

        $buyerOrders = Order::query()
            ->where('buyer_user_id', (int) Auth::id())
            ->with([
                'primaryProduct',
                'orderItems',
                'seller',
                'paymentIntents',
                'paymentTransactions',
                'escrowAccount.escrowEvents',
                'orderStateTransitions',
                'disputeCases',
            ])
            ->latest('id')
            ->limit(20)
            ->get();
        $favoriteStores = $buyerOrders
            ->groupBy(static fn (Order $order): string => (string) ($order->seller?->display_name ?: 'Seller #'.(int) ($order->seller_user_id ?? 0)))
            ->map(static fn ($orders, string $label): array => [
                'id' => Str::slug($label) ?: 'seller',
                'name' => $label,
                'orders' => $orders->count(),
                'active' => $orders->contains(static fn (Order $order): bool => $order->completed_at === null),
            ])
            ->values()
            ->take(8)
            ->all();

        $notificationPreferences = UserNotificationPreference::query()->firstOrCreate(
            ['user_id' => (int) Auth::id()],
            [
                'in_app_enabled' => true,
                'email_enabled' => true,
                'order_updates_enabled' => true,
                'promotion_enabled' => false,
            ],
        );

        return [
            'wallets' => $wallets->all(),
            'walletSummary' => [
                'available' => number_format((float) $walletSummary['available'], 2, '.', ''),
                'held' => number_format((float) $walletSummary['held'], 2, '.', ''),
                'topUps' => (int) $walletSummary['topUps'],
                'transactions' => (int) $walletSummary['transactions'],
            ],
            'paymentMethods' => UserPaymentMethod::query()
                ->where('user_id', (int) Auth::id())
                ->orderByDesc('is_default')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(static fn (UserPaymentMethod $method): array => [
                    'id' => (int) $method->id,
                    'kind' => (string) $method->kind,
                    'label' => (string) $method->label,
                    'subtitle' => (string) ($method->subtitle ?? ''),
                    'details' => is_array($method->details_json ?? null) ? $method->details_json : [],
                    'isDefault' => (bool) $method->is_default,
                    'is_default' => (bool) $method->is_default,
                ])->values()->all(),
            'notifications' => Notification::query()
                ->forPanel((int) Auth::id(), Notification::ROLE_BUYER)
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(static fn (Notification $notification): array => NotificationPresenter::present($notification))
                ->values()
                ->all(),
            'unreadNotificationCount' => Notification::unreadCountForRole((int) Auth::id(), Notification::ROLE_BUYER),
            'notificationPreferences' => [
                'inAppEnabled' => (bool) $notificationPreferences->in_app_enabled,
                'emailEnabled' => (bool) $notificationPreferences->email_enabled,
                'orderUpdatesEnabled' => (bool) $notificationPreferences->order_updates_enabled,
                'promotionEnabled' => (bool) $notificationPreferences->promotion_enabled,
                'securityAlertsEnabled' => (bool) $notificationPreferences->in_app_enabled,
            ],
            'addresses' => UserAddress::query()
                ->where('user_id', (int) Auth::id())
                ->orderByDesc('is_default')
                ->latest('id')
                ->get()
                ->map(static fn (UserAddress $address): array => [
                    'id' => (int) $address->id,
                    'label' => (string) ($address->label ?? ''),
                    'addressType' => (string) ($address->address_type ?? 'shipping'),
                    'recipientName' => (string) $address->recipient_name,
                    'phone' => (string) ($address->phone ?? ''),
                    'addressLine' => (string) $address->address_line,
                    'city' => (string) ($address->city ?? ''),
                    'region' => (string) ($address->region ?? ''),
                    'postalCode' => (string) ($address->postal_code ?? ''),
                    'country' => (string) ($address->country ?? ''),
                    'isDefault' => (bool) $address->is_default,
                ])->values()->all(),
            'devices' => PushDevice::query()
                ->where('user_id', (int) Auth::id())
                ->latest('last_seen_at')
                ->limit(10)
                ->get()
                ->map(static fn (PushDevice $device): array => [
                    'id' => (int) $device->id,
                    'platform' => (string) $device->platform,
                    'name' => (string) ($device->device_name ?? 'Unnamed device'),
                    'active' => (bool) $device->is_active,
                    'lastSeenAt' => $device->last_seen_at?->toIso8601String(),
                ])->values()->all(),
            'activity' => collect(AuditLog::query()
                ->where('actor_user_id', (int) Auth::id())
                ->latest('created_at')
                ->limit(12)
                ->get()
                ->map(static fn (AuditLog $log): array => [
                    'id' => (int) $log->id,
                    'action' => (string) ($log->action ?? 'activity'),
                    'reasonCode' => (string) ($log->reason_code ?? ''),
                    'targetType' => (string) ($log->target_type ?? ''),
                    'targetId' => (int) ($log->target_id ?? 0),
                    'createdAt' => $log->created_at?->toIso8601String(),
                ]))
                ->concat($wallets->flatMap(static fn (array $wallet): array => collect($wallet['recentTopUps'] ?? [])->map(static fn (array $topUp): array => [
                    'id' => 'topup-'.$topUp['id'],
                    'action' => 'buyer.wallet.topup_request',
                    'reasonCode' => (string) ($topUp['status'] ?? 'pending'),
                    'targetType' => 'wallet_top_up',
                    'targetId' => (int) ($topUp['id'] ?? 0),
                    'createdAt' => $topUp['createdAt'] ?? null,
                ])->all())->all())
                ->concat($buyerOrders->map(static fn (Order $order): array => [
                    'id' => 'order-'.$order->id,
                    'action' => 'buyer.order.placed',
                    'reasonCode' => (string) ($order->status instanceof \BackedEnum ? $order->status->value : $order->status),
                    'targetType' => 'order',
                    'targetId' => (int) $order->id,
                    'createdAt' => $order->placed_at?->toIso8601String(),
                ]))
                ->concat(Notification::query()
                    ->forPanel((int) Auth::id(), Notification::ROLE_BUYER)
                    ->latest('id')
                    ->limit(6)
                    ->get()
                    ->map(static fn (Notification $notification): array => [
                        'id' => 'notification-'.$notification->id,
                        'action' => 'buyer.notification.received',
                        'reasonCode' => (string) ($notification->template_code ?? $notification->channel),
                        'targetType' => 'notification',
                        'targetId' => (int) $notification->id,
                        'createdAt' => $notification->created_at?->toIso8601String(),
                    ]))
                ->concat(UserAddress::query()
                    ->where('user_id', (int) Auth::id())
                    ->latest('updated_at')
                    ->limit(6)
                    ->get()
                    ->map(static fn (UserAddress $address): array => [
                        'id' => 'address-'.$address->id,
                        'action' => 'buyer.address.saved',
                        'reasonCode' => (string) ($address->address_type ?? 'shipping'),
                        'targetType' => 'user_address',
                        'targetId' => (int) $address->id,
                        'createdAt' => $address->updated_at?->toIso8601String(),
                    ]))
                ->concat(PushDevice::query()
                    ->where('user_id', (int) Auth::id())
                    ->latest('last_seen_at')
                    ->limit(4)
                    ->get()
                    ->map(static fn (PushDevice $device): array => [
                        'id' => 'device-'.$device->id,
                        'action' => 'buyer.device.seen',
                        'reasonCode' => (string) ($device->platform ?? 'device'),
                        'targetType' => 'push_device',
                        'targetId' => (int) $device->id,
                        'createdAt' => $device->last_seen_at?->toIso8601String(),
                    ]))
                ->filter(static fn (array $item): bool => ! empty($item['createdAt']))
                ->sortByDesc(static fn (array $item): int => strtotime((string) $item['createdAt']) ?: 0)
                ->unique(static fn (array $item): string => (string) $item['id'])
                ->take(16)
                ->values()
                ->all(),
            'recentlyViewed' => $this->recentlyViewedPayload(),
            'favoriteStores' => $favoriteStores,
            'reviews' => Review::query()
                ->where('buyer_user_id', (int) Auth::id())
                ->with(['product', 'seller_profile'])
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (Review $review): array => [
                    'id' => (int) $review->id,
                    'product' => (string) ($review->product?->title ?? 'Marketplace listing'),
                    'seller' => (string) ($review->seller_profile?->display_name ?? 'Seller'),
                    'rating' => (int) $review->rating,
                    'comment' => (string) ($review->comment ?? ''),
                    'createdAt' => $review->created_at?->toIso8601String(),
                ])->values()->all(),
            'returns' => ReturnRequest::query()
                ->where('buyer_user_id', (int) Auth::id())
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(static fn (ReturnRequest $return): array => [
                    'id' => (int) $return->id,
                    'code' => (string) ($return->rma_code ?? 'RMA-'.$return->id),
                    'status' => (string) $return->status,
                    'refundStatus' => (string) ($return->refund_status ?? ''),
                    'reason' => (string) ($return->reason_code ?? ''),
                    'trackingUrl' => (string) ($return->return_tracking_url ?? ''),
                    'carrier' => (string) ($return->return_carrier ?? ''),
                    'requestedAt' => $return->requested_at?->toIso8601String(),
                ])->values()->all(),
            'escrows' => EscrowAccount::query()
                ->whereHas('order', static fn ($query) => $query->where('buyer_user_id', (int) Auth::id()))
                ->with(['order.primaryProduct', 'escrowEvents'])
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(static fn (EscrowAccount $escrow): array => [
                    'id' => (int) $escrow->id,
                    'orderNumber' => (string) ($escrow->order?->order_number ?? 'Order #'.$escrow->order_id),
                    'product' => (string) ($escrow->order?->primaryProduct?->title ?? 'Marketplace order'),
                    'state' => (string) $escrow->state->value,
                    'currency' => (string) ($escrow->currency ?? $escrow->order?->currency ?? 'USD'),
                    'heldAmount' => (string) $escrow->held_amount,
                    'releasedAmount' => (string) $escrow->released_amount,
                    'refundedAmount' => (string) $escrow->refunded_amount,
                    'heldAt' => $escrow->held_at?->toIso8601String(),
                    'timeline' => $escrow->escrowEvents
                        ->sortByDesc('id')
                        ->take(6)
                        ->map(static fn (EscrowEvent $event): array => [
                            'id' => (int) $event->id,
                            'type' => (string) $event->event_type->value,
                            'amount' => (string) $event->amount,
                            'fromState' => (string) ($event->from_state ?? ''),
                            'toState' => (string) ($event->to_state ?? ''),
                            'createdAt' => $event->created_at?->toIso8601String(),
                        ])->values()->all(),
                ])->values()->all(),
            'ordersDetailed' => $buyerOrders->map(function (Order $order): array {
                $latestIntent = $order->paymentIntents->sortByDesc('id')->first();
                $latestTxn = $order->paymentTransactions->sortByDesc('id')->first();
                $status = $order->status instanceof \BackedEnum ? (string) $order->status->value : (string) $order->status;
                $escrowState = $order->escrowAccount?->state?->value ?? null;
                $orderTimeline = $order->orderStateTransitions
                    ->sortByDesc('created_at')
                    ->take(8)
                    ->map(static fn (OrderStateTransition $transition): array => [
                        'id' => (int) $transition->id,
                        'from' => (string) ($transition->from_state ?? ''),
                        'to' => (string) ($transition->to_state ?? ''),
                        'reason' => (string) ($transition->reason_code ?? ''),
                        'createdAt' => $transition->created_at?->toIso8601String(),
                    ])->values()->all();

                return [
                    'id' => (int) $order->id,
                    'code' => (string) ($order->order_number ?? 'SO-'.$order->id),
                    'product' => (string) ($order->primaryProduct?->title ?? $order->product_type ?? 'Marketplace order'),
                    'image' => $this->imageUrl($order->primaryProduct?->image_url),
                    'seller' => (string) ($order->seller?->display_name ?: 'Seller #'.(int) ($order->seller_user_id ?? 0)),
                    'sellerProfileId' => (int) ($order->orderItems->first()?->seller_profile_id ?? 0),
                    'sellerProfileHref' => ($order->orderItems->first()?->seller_profile_id ?? null) ? '/profiles/sellers/'.(int) $order->orderItems->first()->seller_profile_id : null,
                    'status' => $status,
                    'paymentStatus' => $latestIntent?->status ?? $status,
                    'paymentMethod' => $latestTxn?->raw_payload_json['method']
                        ?? $latestTxn?->raw_payload_json['payment_method']
                        ?? $latestIntent?->provider
                        ?? 'wallet',
                    'currency' => (string) ($order->currency ?? 'USD'),
                    'amount' => (float) $order->net_amount,
                    'grossAmount' => (float) $order->gross_amount,
                    'escrowState' => $escrowState,
                    'fulfillmentState' => (string) ($order->fulfillment_state ?? ''),
                    'shippingMethod' => (string) ($order->shipping_method ?? ''),
                    'shippingAddress' => (string) ($order->shipping_address_line ?? ''),
                    'trackingId' => (string) ($order->tracking_id ?? ''),
                    'trackingUrl' => (string) ($order->tracking_url ?? ''),
                    'courier' => (string) ($order->courier_company ?? ''),
                    'placedAt' => $order->placed_at?->toIso8601String(),
                    'deliveredAt' => $order->delivered_at?->toIso8601String(),
                    'buyerReviewExpiresAt' => $order->buyer_review_expires_at?->toIso8601String(),
                    'timeline' => $orderTimeline,
                    'hasDispute' => $order->disputeCases->isNotEmpty(),
                ];
            })->values()->all(),
            'savedItems' => collect($this->cartPayload())
                ->map(static fn (array $item): array => [
                    'id' => (int) ($item['id'] ?? 0),
                    'title' => (string) ($item['title'] ?? 'Saved item'),
                    'price' => (float) ($item['price'] ?? 0),
                    'image' => (string) ($item['image'] ?? ''),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                ])->values()->all(),
            'selectedEscrowOrder' => $this->selectedEscrowOrderDetailPayload(),
            'security' => [
                'accountStatus' => (string) ($user?->status ?? 'active'),
                'lastLoginAt' => $user?->last_login_at?->toIso8601String(),
                'phone' => (string) ($user?->phone ?? ''),
                'email' => (string) ($user?->email ?? ''),
                'twoFactorEnabled' => false,
            ],
        ];
    }

    private function fillBuyerAddress(UserAddress $address, Request $request): void
    {
        $payload = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'address_type' => ['nullable', 'string', 'in:shipping,billing'],
            'recipient_name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address_line' => ['required', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $address->fill([
            'label' => $payload['label'] ?? null,
            'address_type' => $payload['address_type'] ?? 'shipping',
            'recipient_name' => $payload['recipient_name'],
            'phone' => $payload['phone'] ?? null,
            'address_line' => $payload['address_line'],
            'city' => $payload['city'] ?? null,
            'region' => $payload['region'] ?? null,
            'postal_code' => $payload['postal_code'] ?? null,
            'country' => $payload['country'] ?? null,
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ]);
    }

    private function writeBuyerAudit(string $action, string $reasonCode, string $targetType = 'user', ?int $targetId = null): void
    {
        if (! Auth::check()) {
            return;
        }

        AuditLog::query()->create([
            'uuid' => (string) Str::uuid(),
            'actor_user_id' => (int) Auth::id(),
            'action' => $action,
            'reason_code' => $reasonCode,
            'target_type' => $targetType,
            'target_id' => $targetId ?? (int) Auth::id(),
            'before_json' => [],
            'after_json' => [],
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function recentlyViewedPayload(): array
    {
        $ids = collect(request()->session()->get('web_recently_viewed', []))
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->take(12)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $ids->all())
            ->with(['seller_profile', 'category.parent', 'inventoryRecords', 'productVariants.inventoryRecords'])
            ->withCount([
                'reviews' => static fn ($query) => $query->where('status', 'visible'),
                'orderItems',
            ])
            ->withAvg([
                'reviews as reviews_avg_rating' => static fn ($query) => $query->where('status', 'visible'),
            ], 'rating')
            ->get()
            ->map(fn (Product $product): array => $this->productPayload($product))
            ->keyBy('id');

        return $ids
            ->map(static fn (int $id): ?array => $products->get($id))
            ->filter()
            ->values()
            ->all();
    }

    private function sellerProductsPayload(): array
    {
        $user = Auth::user();
        if ($user?->sellerProfile !== null) {
            return Product::query()
                ->where('seller_profile_id', $user->sellerProfile->id)
                ->with(['seller_profile', 'category.parent', 'inventoryRecords', 'productVariants.inventoryRecords'])
                ->withCount([
                    'reviews' => static fn ($query) => $query->where('status', 'visible'),
                    'orderItems',
                ])
                ->withAvg([
                    'reviews as reviews_avg_rating' => static fn ($query) => $query->where('status', 'visible'),
                ], 'rating')
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
        $seller = Auth::user()?->sellerProfile;
        $promotions = Promotion::query()
            ->where('campaign_type', 'coupon')
            ->when($seller instanceof SellerProfile, static function ($query) use ($seller): void {
                $query->where(function ($sellerQuery) use ($seller): void {
                    $sellerQuery
                        ->where('created_by_user_id', Auth::id())
                        ->orWhereJsonContains('target_seller_profile_ids', (int) $seller->id)
                        ->orWhere('scope_type', 'all');
                });
            })
            ->latest('id')
            ->limit(24)
            ->get()
            ->map(fn (Promotion $promotion): array => $this->sellerCouponPayload($promotion))
            ->values()
            ->all();

        return $promotions;
    }

    private function sellerCouponPayload(Promotion $promotion): array
    {
        $discountType = (string) $promotion->discount_type;
        $discountValue = $discountType === 'percentage'
            ? round((float) $promotion->discount_value * 100, 2)
            : (float) $promotion->discount_value;
        $startsAt = $promotion->starts_at;
        $endsAt = $promotion->ends_at;
        $now = now();
        $isScheduled = $startsAt !== null && $startsAt->greaterThan($now);
        $isExpired = $endsAt !== null && $endsAt->lessThan($now);
        $usageLimit = $promotion->usage_limit !== null ? (int) $promotion->usage_limit : null;

        return [
            'id' => (int) $promotion->id,
            'code' => (string) $promotion->code,
            'title' => (string) $promotion->title,
            'description' => (string) ($promotion->description ?? ''),
            'badge' => (string) ($promotion->badge ?? ''),
            'type' => $discountType,
            'value' => $discountValue,
            'currency' => (string) $promotion->currency,
            'status' => ! $promotion->is_active ? 'Paused' : ($isExpired ? 'Expired' : ($isScheduled ? 'Scheduled' : 'Active')),
            'isActive' => (bool) $promotion->is_active,
            'usage' => (int) $promotion->used_count,
            'usageLimit' => $usageLimit,
            'remaining' => $usageLimit !== null ? max(0, $usageLimit - (int) $promotion->used_count) : null,
            'minSpend' => (float) $promotion->min_spend,
            'maxDiscountAmount' => $promotion->max_discount_amount !== null ? (float) $promotion->max_discount_amount : null,
            'startsAt' => $startsAt?->toIso8601String(),
            'endsAt' => $endsAt?->toIso8601String(),
            'dailyStartTime' => $promotion->daily_start_time,
            'dailyEndTime' => $promotion->daily_end_time,
            'scopeType' => (string) ($promotion->scope_type ?? 'sellers'),
            'priority' => (int) ($promotion->priority ?? 250),
            'marketingChannel' => (string) ($promotion->marketing_channel ?? 'seller_web'),
            'createdAt' => $promotion->created_at?->format('M j, Y H:i'),
            'updatedAt' => $promotion->updated_at?->format('M j, Y H:i'),
        ];
    }

    private function payoutPayload(): array
    {
        $seller = Auth::user()?->sellerProfile;
        if ($seller === null) {
            return [];
        }

        return WithdrawalRequest::query()
            ->where('seller_profile_id', $seller->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(static function (WithdrawalRequest $withdrawal): array {
                $status = $withdrawal->status instanceof \BackedEnum
                    ? (string) $withdrawal->status->value
                    : (string) $withdrawal->status;

                return [
                    'id' => 'PO-'.$withdrawal->id,
                    'amount' => (float) $withdrawal->requested_amount,
                    'status' => $status,
                    'method' => 'Wallet payout',
                    'createdAt' => $withdrawal->created_at?->format('M j, Y H:i'),
                ];
            })
            ->values()
            ->all();
    }

    private function businessPayload(): array
    {
        $seller = Auth::user()?->sellerProfile;
        if ($seller === null) {
            return request()->session()->get('web_business', [
                'name' => 'Guest seller workspace',
                'storeDescription' => '',
                'storeLogoUrl' => '',
                'bannerImageUrl' => '',
                'contactEmail' => '',
                'phone' => '',
                'address' => '',
                'addressLine' => '',
                'city' => '',
                'region' => '',
                'postalCode' => '',
                'country' => '',
                'verification' => 'Guest preview',
            ]);
        }

        return [
            'name' => (string) ($seller->display_name ?? 'Seller store'),
            'storeDescription' => (string) ($seller->legal_name ?? ''),
            'storeLogoUrl' => $this->imageUrl($seller->store_logo_url),
            'bannerImageUrl' => $this->imageUrl($seller->banner_image_url),
            'contactEmail' => (string) ($seller->contact_email ?? ''),
            'phone' => (string) ($seller->contact_phone ?? ''),
            'address' => trim(implode(', ', array_filter([$seller->address_line, $seller->city, $seller->country]))),
            'addressLine' => (string) ($seller->address_line ?? ''),
            'city' => (string) ($seller->city ?? ''),
            'region' => (string) ($seller->region ?? ''),
            'postalCode' => (string) ($seller->postal_code ?? ''),
            'country' => (string) ($seller->country ?? ''),
            'verification' => (string) $seller->verification_status,
        ];
    }

    private function sellerOperationsPayload(): array
    {
        $seller = Auth::user()?->sellerProfile;
        if (! $seller instanceof SellerProfile) {
            return [
                'warehouses' => [],
                'shippingSettings' => null,
                'payoutMethods' => [],
                'reviews' => [],
                'notifications' => [],
                'returns' => [],
                'disputes' => [],
                'kyc' => null,
            ];
        }

        $products = Product::query()
            ->where('seller_profile_id', $seller->id)
            ->with(['inventoryRecords', 'category.parent'])
            ->limit(100)
            ->get();
        $stockOnHand = (int) $products->sum(static fn (Product $product): int => (int) $product->inventoryRecords->sum('stock_on_hand'));
        $reserved = (int) $products->sum(static fn (Product $product): int => (int) $product->inventoryRecords->sum('stock_reserved'));
        $sold = (int) $products->sum(static fn (Product $product): int => (int) $product->inventoryRecords->sum('stock_sold'));
        $warehouseRows = Schema::hasTable('seller_warehouses')
            ? SellerWarehouse::query()->where('seller_profile_id', $seller->id)->latest('id')->get()
            : collect();
        if ($warehouseRows->isEmpty() && Schema::hasTable('seller_warehouses')) {
            $warehouseRows = collect([SellerWarehouse::query()->create([
                'uuid' => (string) Str::uuid(),
                'seller_profile_id' => $seller->id,
                'name' => (string) (($seller->city ?: 'Main').' Warehouse'),
                'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $seller->city ?: 'MAIN'), 0, 4)) ?: 'MAIN',
                'city' => (string) ($seller->city ?? ''),
                'status' => 'active',
            ])]);
        }
        $shippingMethods = SellerShippingMethod::query()
            ->where('seller_profile_id', $seller->id)
            ->with('shippingMethod')
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (SellerShippingMethod $method): array => [
                'id' => (int) $method->id,
                'shippingMethodId' => (int) $method->shipping_method_id,
                'code' => (string) ($method->shippingMethod?->code ?? ''),
                'name' => (string) ($method->shippingMethod?->name ?? 'Shipping method'),
                'suggestedFee' => (float) ($method->shippingMethod?->suggested_fee ?? 0),
                'price' => (float) $method->price,
                'processingTime' => (string) ($method->processing_time_label ?? ''),
                'enabled' => (bool) $method->is_enabled,
                'sortOrder' => (int) $method->sort_order,
            ])
            ->values()
            ->all();
        $availableShippingMethods = ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static fn (ShippingMethod $method): array => [
                'id' => (int) $method->id,
                'code' => (string) $method->code,
                'name' => (string) $method->name,
                'suggestedFee' => (float) $method->suggested_fee,
                'processingTime' => (string) $method->processing_time_label,
                'sortOrder' => (int) $method->sort_order,
            ])
            ->values()
            ->all();

        return [
            'warehouses' => $warehouseRows->map(static fn (SellerWarehouse $warehouse): array => [
                'id' => (int) $warehouse->id,
                'name' => (string) $warehouse->name,
                'code' => (string) ($warehouse->code ?? 'WH'),
                'address' => (string) ($warehouse->address ?? ''),
                'city' => (string) ($warehouse->city ?? ''),
                'contactPerson' => (string) ($warehouse->contact_person ?? ''),
                'phone' => (string) ($warehouse->phone ?? ''),
                'status' => (string) $warehouse->status,
                'active' => $warehouse->status === 'active',
                'stockOnHand' => $stockOnHand,
                'reserved' => $reserved,
                'available' => max(0, $stockOnHand - $reserved),
                'sold' => $sold,
                'listings' => (int) $products->count(),
            ])->values()->all(),
            'stockMovements' => Schema::hasTable('stock_movements') ? StockMovement::query()
                ->where('seller_profile_id', $seller->id)
                ->with(['product', 'productVariant', 'warehouse'])
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(static fn (StockMovement $movement): array => [
                    'id' => (int) $movement->id,
                    'type' => (string) $movement->movement_type,
                    'product' => (string) ($movement->product?->title ?? 'Product'),
                    'variant' => (string) ($movement->productVariant?->title ?? ''),
                    'warehouse' => (string) ($movement->warehouse?->name ?? 'Main Warehouse'),
                    'delta' => (int) $movement->quantity_delta,
                    'stockAfter' => (int) $movement->stock_after,
                    'reason' => (string) ($movement->reason ?? ''),
                    'reference' => (string) ($movement->reference ?? ''),
                    'createdAt' => $movement->created_at?->format('M j, Y H:i'),
                ])->values()->all() : [],
            'wallet' => $this->walletPayload($seller),
            'shippingSettings' => [
                'insideDhakaLabel' => (string) ($seller->inside_dhaka_label ?? 'Inside Dhaka'),
                'insideDhakaFee' => (float) ($seller->inside_dhaka_fee ?? 0),
                'outsideDhakaLabel' => (string) ($seller->outside_dhaka_label ?? 'Outside Dhaka'),
                'outsideDhakaFee' => (float) ($seller->outside_dhaka_fee ?? 0),
                'cashOnDeliveryEnabled' => (bool) ($seller->cash_on_delivery_enabled ?? false),
                'processingTimeLabel' => (string) ($seller->processing_time_label ?? ''),
                'methods' => $shippingMethods,
                'availableMethods' => $availableShippingMethods,
                'processingTimeOptions' => ['Instant', 'Same day', '1-2 Business Days', '3-5 Business Days', '5-7 Business Days'],
            ],
            'payoutMethods' => PayoutAccount::query()
                ->where('seller_profile_id', $seller->id)
                ->latest('is_default')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (PayoutAccount $account): array => $this->payoutAccountPayload($account))
                ->values()
                ->all(),
            'reviews' => Review::query()
                ->where('seller_profile_id', $seller->id)
                ->with(['buyer', 'product'])
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(static fn (Review $review): array => [
                    'id' => (int) $review->id,
                    'buyer' => (string) ($review->buyer?->display_name ?: 'Buyer #'.(int) $review->buyer_user_id),
                    'buyerProfileHref' => '/profiles/buyers/'.(int) $review->buyer_user_id,
                    'product' => (string) ($review->product?->title ?? 'Marketplace listing'),
                    'rating' => (int) $review->rating,
                    'feedbackType' => (string) ($review->feedback_type ?? $this->feedbackTypeForRating((int) $review->rating)),
                    'comment' => (string) ($review->comment ?? ''),
                    'tags' => $review->tags ?? [],
                    'status' => (string) $review->status,
                    'sellerReply' => (string) ($review->seller_reply ?? ''),
                    'sellerRepliedAt' => $review->seller_replied_at?->format('M j, Y'),
                    'sellerProfileId' => (int) $review->seller_profile_id,
                    'helpfulCount' => (int) ($review->helpful_count ?? 0),
                    'createdAt' => $review->created_at?->format('M j, Y'),
                ])
                ->values()
                ->all(),
            'notifications' => Notification::query()
                ->forPanel((int) $seller->user_id, Notification::ROLE_SELLER)
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(static fn (Notification $notification): array => NotificationPresenter::present($notification))
                ->values()
                ->all(),
            'unreadNotificationCount' => Notification::unreadCountForRole((int) $seller->user_id, Notification::ROLE_SELLER),
            'returns' => ReturnRequest::query()
                ->where('seller_user_id', $seller->user_id)
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(static fn (ReturnRequest $return): array => [
                    'id' => (int) $return->id,
                    'code' => (string) ($return->rma_code ?? 'RMA-'.$return->id),
                    'reason' => (string) $return->reason_code,
                    'status' => (string) $return->status,
                    'refundStatus' => (string) $return->refund_status,
                    'dueAt' => $return->sla_due_at?->format('M j'),
                ])
                ->values()
                ->all(),
            'disputes' => DisputeCase::query()
                ->whereHas('order_item', static fn ($query) => $query->where('seller_profile_id', $seller->id))
                ->with(['order'])
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(static function (DisputeCase $case): array {
                    $status = $case->status instanceof \BackedEnum ? (string) $case->status->value : (string) $case->status;

                    return [
                        'id' => (int) $case->id,
                        'order' => (string) ($case->order?->order_number ?? 'Order #'.$case->order_id),
                        'status' => $status,
                        'openedAt' => $case->opened_at?->format('M j, Y'),
                        'reason' => (string) ($case->escalation_reason ?? 'Buyer dispute'),
                    ];
                })
                ->values()
                ->all(),
            'ordersDetailed' => Order::query()
                ->where('seller_user_id', (int) $seller->user_id)
                ->with(['primaryProduct', 'buyer', 'escrowAccount', 'latestDigitalDelivery.files'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(function (Order $order): array {
                    $delivery = $order->latestDigitalDelivery;

                    return [
                        'id' => (int) $order->id,
                        'code' => (string) ($order->order_number ?? 'SO-'.$order->id),
                        'product' => (string) ($order->primaryProduct?->title ?? 'Marketplace order'),
                        'image' => $this->imageUrl($order->primaryProduct?->image_url),
                        'buyer' => (string) ($order->buyer?->display_name ?: 'Buyer #'.(int) $order->buyer_user_id),
                        'buyerId' => (int) $order->buyer_user_id,
                        'buyerProfileHref' => '/profiles/buyers/'.(int) $order->buyer_user_id,
                        'status' => (string) $order->status->value,
                        'escrowState' => (string) ($order->escrow_status ?: ($order->escrowAccount?->state?->value ?? '')),
                        'deliveryStatus' => (string) ($order->delivery_status ?? ($delivery?->status ?? 'pending')),
                        'amount' => (float) $order->net_amount,
                        'placedAt' => $order->placed_at?->toIso8601String(),
                        'buyerReviewExpiresAt' => $order->buyer_review_expires_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'selectedEscrowOrder' => $this->selectedEscrowOrderDetailPayload(),
            'kyc' => $this->sellerKycPayload($seller),
        ];
    }

    private function selectedEscrowOrderDetailPayload(): ?array
    {
        if (! Auth::check()) {
            return null;
        }

        $orderId = (int) request()->query('order', 0);
        if ($orderId <= 0) {
            return null;
        }

        $order = Order::query()
            ->with(['buyer', 'seller', 'primaryProduct', 'orderItems', 'escrowAccount.escrowEvents', 'orderStateTransitions', 'disputeCases', 'latestDigitalDelivery.files'])
            ->find($orderId);

        if (! $order instanceof Order) {
            return null;
        }

        $viewerId = (int) Auth::id();
        if (! in_array($viewerId, [(int) $order->buyer_user_id, (int) ($order->seller_user_id ?? 0)], true)) {
            return null;
        }

        return app(EscrowOrderDetailService::class)->build($order, $viewerId);
    }

    private function authorizeOrderParticipant(Order $order, ?string $expectedRole = null): int
    {
        abort_unless(Auth::check(), 403);
        $viewerId = (int) Auth::id();
        $isBuyer = (int) $order->buyer_user_id === $viewerId;
        $isSeller = (int) ($order->seller_user_id ?? 0) === $viewerId;
        abort_unless($isBuyer || $isSeller, 404);

        if ($expectedRole === 'buyer') {
            abort_unless($isBuyer, 403);
        }
        if ($expectedRole === 'seller') {
            abort_unless($isSeller, 403);
        }

        return $viewerId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sellerKycPayload(SellerProfile $seller): ?array
    {
        $kyc = KycVerification::query()
            ->where('seller_profile_id', $seller->id)
            ->with(['kycDocuments', 'statusHistories'])
            ->latest('id')
            ->first();
        if (! $kyc instanceof KycVerification) {
            return [
                'id' => null,
                'status' => 'not_submitted',
                'statusLabel' => 'Not submitted',
                'personal' => [],
                'business' => [],
                'bank' => [],
                'address' => [],
                'documents' => [],
                'requiredDocuments' => $this->requiredKycDocuments(),
                'timeline' => [],
                'providerSessionUrl' => null,
                'rejectionReason' => null,
                'expiresAt' => null,
            ];
        }

        $bank = is_array($kyc->bank_info_encrypted) ? $kyc->bank_info_encrypted : [];
        if (isset($bank['account_number'])) {
            $bank['account_number_masked'] = $this->maskSensitive((string) $bank['account_number']);
        }
        if (isset($bank['mobile_banking_number'])) {
            $bank['mobile_banking_number_masked'] = $this->maskSensitive((string) $bank['mobile_banking_number']);
        }
        $personal = is_array($kyc->personal_info_encrypted) ? $kyc->personal_info_encrypted : [];
        if (isset($personal['id_number'])) {
            $personal['id_number_masked'] = $this->maskSensitive((string) $personal['id_number']);
        }

        return [
            'id' => (int) $kyc->id,
            'status' => (string) $kyc->status,
            'statusLabel' => Str::headline((string) $kyc->status),
            'personal' => $personal,
            'business' => is_array($kyc->business_info_encrypted) ? $kyc->business_info_encrypted : [],
            'bank' => $bank,
            'address' => is_array($kyc->address_info_encrypted) ? $kyc->address_info_encrypted : [],
            'documents' => $kyc->kycDocuments->map(fn (KycDocument $document): array => $this->kycDocumentPayload($document))->values()->all(),
            'requiredDocuments' => $this->requiredKycDocuments((string) ($personal['identity_document_type'] ?? 'nid')),
            'timeline' => $kyc->statusHistories->sortByDesc('id')->map(static fn (KycStatusHistory $history): array => [
                'id' => (int) $history->id,
                'from' => $history->from_status,
                'to' => $history->to_status,
                'reason' => $history->reason_code,
                'note' => $history->note,
                'createdAt' => $history->created_at?->format('M j, Y H:i'),
            ])->values()->all(),
            'providerSessionUrl' => $kyc->provider_session_url,
            'providerSessionId' => $kyc->provider_session_id,
            'providerResult' => $kyc->provider_result_json,
            'riskLevel' => $kyc->risk_level,
            'rejectionReason' => $kyc->rejection_reason,
            'submittedAt' => $kyc->submitted_at?->format('M j, Y H:i'),
            'reviewedAt' => $kyc->reviewed_at?->format('M j, Y H:i'),
            'expiresAt' => $kyc->expires_at?->format('M j, Y'),
        ];
    }

    private function maskSensitive(string $value): string
    {
        $value = trim($value);
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }

    private function payoutAccountPayload(PayoutAccount $account): array
    {
        $details = json_decode((string) $account->account_ref_token, true);
        if (! is_array($details)) {
            $details = [];
        }

        $methodType = (string) ($details['method_type'] ?? '');
        if (! in_array($methodType, ['bkash', 'nagad', 'bank_transfer'], true)) {
            $methodType = match ((string) $account->account_type) {
                'bank' => 'bank_transfer',
                'mobile_money' => in_array((string) $account->provider, ['bkash', 'nagad'], true) ? (string) $account->provider : 'bkash',
                default => 'bank_transfer',
            };
        }

        $accountNumber = (string) ($details['account_number'] ?? $account->account_ref_token ?? '');
        $bankName = (string) ($details['bank_name'] ?? '');

        return [
            'id' => (int) $account->id,
            'methodType' => $methodType,
            'method_type' => $methodType,
            'type' => $methodType,
            'label' => match ($methodType) {
                'bkash' => 'bKash',
                'nagad' => 'Nagad',
                default => 'Bank Transfer',
            },
            'provider' => $methodType === 'bank_transfer' ? ($bankName !== '' ? $bankName : (string) $account->provider) : (string) $account->provider,
            'providerName' => (string) ($account->provider ?? ''),
            'accountName' => (string) ($details['account_name'] ?? ''),
            'account_name' => (string) ($details['account_name'] ?? ''),
            'account' => $this->maskSensitive($accountNumber),
            'accountNumberMasked' => $this->maskSensitive($accountNumber),
            'account_number_masked' => $this->maskSensitive($accountNumber),
            'bankName' => $bankName !== '' ? $bankName : (string) ($account->provider ?? ''),
            'branchName' => (string) ($details['branch_name'] ?? ''),
            'routingNumber' => (string) ($details['routing_number'] ?? ''),
            'accountTypeLabel' => (string) ($details['account_type_label'] ?? ''),
            'default' => (bool) $account->is_default,
            'isDefault' => (bool) $account->is_default,
            'status' => (string) $account->status,
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
                'from' => (int) $message->sender_user_id === (int) Auth::id() ? 'me' : 'other',
                'fromMe' => (int) $message->sender_user_id === (int) Auth::id(),
                'body' => (string) $message->body,
                'time' => $message->created_at?->format('H:i') ?? '',
            ])
            ->all();
    }

    private function supportMessagePayload(ChatMessage $message): array
    {
        $senderRole = (string) ($message->sender_role ?? '');
        $fromMe = (int) $message->sender_user_id === (int) Auth::id();
        $isSystem = (string) ($message->marker_type ?? '') !== '' || in_array($senderRole, ['system', 'admin'], true);
        $attachmentUrl = '';
        $rawAttachmentUrl = trim((string) ($message->attachment_url ?? ''));
        if ($rawAttachmentUrl !== '') {
            $attachmentUrl = Auth::check()
                ? route('web.actions.support.attachments.preview', ['message' => $message->id])
                : ($this->imageUrl($rawAttachmentUrl) ?? '');
        }

        return [
            'id' => (int) $message->id,
            'from' => $isSystem ? 'system' : ($fromMe ? 'me' : ($senderRole !== '' ? $senderRole : 'buyer')),
            'fromMe' => $fromMe,
            'body' => (string) ($message->body ?? ''),
            'time' => $message->created_at?->format('H:i') ?? '',
            'attachmentUrl' => $attachmentUrl,
            'attachmentName' => (string) ($message->attachment_name ?? ''),
            'attachmentType' => (string) ($message->attachment_type ?? ''),
            'attachmentMime' => (string) ($message->attachment_mime ?? ''),
            'attachmentSize' => $message->attachment_size !== null ? (int) $message->attachment_size : null,
            'isSystem' => $isSystem,
        ];
    }

    private function activeCart(): Cart
    {
        return Cart::query()->firstOrCreate(
            ['buyer_user_id' => Auth::id(), 'status' => 'active'],
            ['uuid' => (string) Str::uuid(), 'expires_at' => now()->addDays(14)]
        );
    }

    private function authenticatedSeller(): ?SellerProfile
    {
        if (! Auth::check()) {
            abort(response()->json(['ok' => false, 'message' => 'Sign in as a seller to continue.'], 401));
        }

        $seller = Auth::user()?->sellerProfile;
        if (! $seller instanceof SellerProfile) {
            abort(response()->json(['ok' => false, 'message' => 'A seller account is required for this action.'], 403));
        }

        return $seller;
    }

    /**
     * @param array<string, mixed> $method
     */
    private function shippingMethodFromPayload(array $method, SellerProfile $seller): ?ShippingMethod
    {
        $methodId = (int) ($method['shipping_method_id'] ?? 0);
        if ($methodId > 0) {
            return ShippingMethod::query()->whereKey($methodId)->where('is_active', true)->first();
        }

        $name = trim((string) ($method['method_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $baseCode = Str::slug($name, '_') ?: 'seller_shipping';
        $code = $baseCode;
        $suffix = 1;
        while (ShippingMethod::query()->where('code', $code)->where('name', '!=', $name)->exists()) {
            $suffix++;
            $code = $baseCode.'_'.$suffix;
        }

        return ShippingMethod::query()->firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'suggested_fee' => (float) ($method['price'] ?? 0),
                'processing_time_label' => (string) ($method['processing_time_label'] ?? $seller->processing_time_label ?? '1-2 Business Days'),
                'is_active' => true,
                'sort_order' => (int) ($method['sort_order'] ?? 100),
            ],
        );
    }

    private function fallbackCategoryId(): int
    {
        $categoryId = Category::query()->where('is_active', true)->value('id') ?: Category::query()->value('id');
        if ($categoryId !== null) {
            return (int) $categoryId;
        }

        $category = Category::query()->firstOrCreate(
            ['slug' => 'general-marketplace'],
            [
                'parent_id' => null,
                'name' => 'General Marketplace',
                'description' => 'Default catalog category for seller-created listings.',
                'image_url' => null,
                'is_active' => true,
                'sort_order' => 1000,
            ],
        );

        return (int) $category->id;
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
                ->map(function (ChatThread $thread): array {
                    $messages = ChatMessage::query()
                        ->where('thread_id', $thread->id)
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get()
                        ->sortBy('id')
                        ->values()
                        ->map(fn (ChatMessage $message): array => $this->supportMessagePayload($message))
                        ->all();

                    return [
                        'id' => 'SUP-'.$thread->id,
                        'threadId' => (int) $thread->id,
                        'subject' => (string) ($thread->subject ?? 'Marketplace support'),
                        'status' => (string) $thread->status,
                        'messages' => $messages,
                    ];
                })
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
                'threadId' => null,
                'subject' => 'Marketplace support conversation',
                'status' => 'Open',
                'messages' => array_values($messages),
            ]];
        }

        return [];
    }

    private function sellerCouponPromotionOrAbort(Promotion $promotion, SellerProfile $seller): Promotion
    {
        abort_unless(
            $promotion->campaign_type === 'coupon'
            && (
                (int) $promotion->created_by_user_id === (int) Auth::id()
                || in_array((int) $seller->id, $promotion->target_seller_profile_ids ?? [], true)
            ),
            403
        );

        return $promotion;
    }

    private function fillSellerCouponPromotion(Promotion $promotion, Request $request, SellerProfile $seller, bool $partial = false): void
    {
        $payload = $request->validate([
            'code' => [$partial ? 'sometimes' : 'required', 'string', 'max:64'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => [$partial ? 'sometimes' : 'required', Rule::in(['percentage', 'fixed', 'shipping'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'daily_start_time' => ['nullable', 'date_format:H:i'],
            'daily_end_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $discountType = (string) ($payload['discount_type'] ?? $promotion->discount_type ?? 'percentage');
        $discountValue = isset($payload['discount_value'])
            ? (float) $payload['discount_value']
            : ($promotion->discount_type === 'percentage' ? (float) $promotion->discount_value * 100 : (float) $promotion->discount_value);

        if ($discountType === 'percentage' && $discountValue > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discounts cannot exceed 100%.',
            ]);
        }

        $title = trim((string) ($payload['title'] ?? $promotion->title ?? 'Seller Offer'));
        $code = Str::upper(trim((string) ($payload['code'] ?? $promotion->code ?? '')));
        $description = trim((string) ($payload['description'] ?? $promotion->description ?? ''));
        $minSpend = isset($payload['min_spend']) ? (float) $payload['min_spend'] : (float) ($promotion->min_spend ?? 0);
        $maxDiscountAmount = array_key_exists('max_discount_amount', $payload)
            ? ($payload['max_discount_amount'] === null || $payload['max_discount_amount'] === '' ? null : (float) $payload['max_discount_amount'])
            : ($promotion->max_discount_amount !== null ? (float) $promotion->max_discount_amount : null);
        $usageLimit = array_key_exists('usage_limit', $payload)
            ? ($payload['usage_limit'] === null || $payload['usage_limit'] === '' ? null : (int) $payload['usage_limit'])
            : $promotion->usage_limit;

        $duplicateQuery = Promotion::query()
            ->where('campaign_type', 'coupon')
            ->where('created_by_user_id', Auth::id())
            ->where('code', $code);
        if ($promotion->exists) {
            $duplicateQuery->whereKeyNot($promotion->id);
        }
        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'code' => 'This coupon code is already in use on your seller account.',
            ]);
        }

        $promotion->fill([
            'code' => $code,
            'title' => $title,
            'description' => $description !== '' ? $description : 'Seller-managed web campaign.',
            'badge' => match ($discountType) {
                'fixed' => $seller->default_currency.' '.number_format($discountValue, 0).' OFF',
                'shipping' => 'FREE SHIPPING',
                default => rtrim(rtrim(number_format($discountValue, 2), '0'), '.').'% OFF',
            },
            'campaign_type' => 'coupon',
            'scope_type' => 'sellers',
            'target_seller_profile_ids' => [(int) $seller->id],
            'target_product_ids' => null,
            'target_category_ids' => null,
            'target_product_types' => null,
            'currency' => (string) ($seller->default_currency ?? 'BDT'),
            'discount_type' => $discountType,
            'discount_value' => $discountType === 'percentage'
                ? number_format($discountValue / 100, 4, '.', '')
                : number_format($discountValue, 4, '.', ''),
            'min_spend' => number_format($minSpend, 4, '.', ''),
            'max_discount_amount' => $maxDiscountAmount !== null ? number_format($maxDiscountAmount, 4, '.', '') : null,
            'starts_at' => $payload['starts_at'] ?? $promotion->starts_at ?? now(),
            'ends_at' => $payload['ends_at'] ?? $promotion->ends_at ?? now()->addDays(30),
            'daily_start_time' => $payload['daily_start_time'] ?? $promotion->daily_start_time,
            'daily_end_time' => $payload['daily_end_time'] ?? $promotion->daily_end_time,
            'usage_limit' => $usageLimit,
            'priority' => 250,
            'marketing_channel' => 'seller_web',
            'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : ($promotion->exists ? (bool) $promotion->is_active : true),
        ]);
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
            'id' => (int) $seller->id,
            'href' => '/profiles/sellers/'.(int) $seller->id,
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
            ['title' => 'Fulfillment Aware', 'body' => 'Physical, digital, and service listings stay clearly labeled.'],
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
            'isAuthenticated' => false,
            'name' => (string) ($profile['name'] ?? 'Guest buyer'),
            'email' => (string) ($profile['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'avatarUrl' => $this->imageUrl((string) ($profile['avatar_url'] ?? '')),
            'role' => 'buyer',
            'roles' => ['buyer'],
            'hasSellerProfile' => false,
            'city' => (string) ($profile['city'] ?? ''),
        ];
    }

    private function feedbackTypeForRating(int $rating): string
    {
        return match (true) {
            $rating >= 4 => 'good',
            $rating <= 2 => 'bad',
            default => 'neutral',
        };
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
        if (str_starts_with($image, 'api/v1/media/')) {
            return '/'.$image;
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return '/api/v1/media/'.str_replace('%2F', '/', rawurlencode(ltrim($image, '/')));
    }

    private function mediaStoragePath(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $value;
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'api/v1/media/')) {
            $path = substr($path, strlen('api/v1/media/'));
        }

        $path = rawurldecode($path);
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'seller-uploads/')) {
            return null;
        }

        return $path;
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

    private function productTypeLabel(string $type, bool $isInstantDelivery = false): string
    {
        if ($type === 'digital' && $isInstantDelivery) {
            return 'Digital product';
        }

        return match ($type) {
            'physical' => 'Physical product',
            'digital' => 'Digital product',
            'service' => 'Service',
            default => ucfirst(str_replace('_', ' ', $type ?: 'Marketplace')),
        };
    }

    private function productTypeHint(string $type, bool $isInstantDelivery = false): string
    {
        if ($type === 'digital' && $isInstantDelivery) {
            return 'Delivered instantly after checkout through automatic digital fulfillment.';
        }

        return match ($type) {
            'physical' => 'Ships from the seller with inventory and delivery tracking.',
            'digital' => 'Delivered digitally after checkout through seller handoff.',
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
            'rating' => isset($snapshot['rating']) && is_numeric($snapshot['rating']) ? (float) $snapshot['rating'] : null,
            'verified' => (bool) ($snapshot['verified'] ?? false),
            'condition' => Str::limit((string) ($snapshot['condition'] ?? 'New'), 40, ''),
            'image' => $this->imageUrl((string) ($snapshot['image'] ?? '')),
            'tags' => array_values(array_slice(array_filter(array_map('strval', (array) ($snapshot['tags'] ?? ['escrow']))), 0, 5)),
            'description' => Str::limit((string) ($snapshot['description'] ?? 'Marketplace listing.'), 5000, ''),
        ];
    }
}

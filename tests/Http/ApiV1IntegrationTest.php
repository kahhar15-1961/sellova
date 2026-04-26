<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Auth\RoleCodes;
use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Http\AppServices;
use App\Http\HttpKernel;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\Storefront;
use App\Models\User;
use App\Models\UserAuthToken;
use App\Models\UserPaymentMethod;
use App\Models\UserRole;
use App\Models\UserWishlistItem;
use App\Models\Notification;
use App\Services\Escrow\EscrowService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;

final class ApiV1IntegrationTest extends TestCase
{
    private HttpKernel $kernel;

    private WalletLedgerService $wallet;

    private EscrowService $escrow;

    protected function setUp(): void
    {
        parent::setUp();

        $app = new AppServices();
        $routeFactory = require __DIR__.'/../../routes/api.php';
        $routes = $routeFactory($app);
        $this->kernel = new HttpKernel($app, $routes);
        $this->wallet = new WalletLedgerService();
        $this->escrow = new EscrowService($this->wallet);
    }

    public function test_auth_session_endpoints_and_opaque_token_middleware_flow(): void
    {
        $reg = $this->legacyApiJson('POST', '/api/v1/auth/register', [
            'account_type' => 'buyer',
            'email' => 'auth-buyer@example.test',
            'password' => 'secret1234',
            'display_name' => 'Buyer',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        self::assertSame(201, $reg['status']);
        self::assertArrayHasKey('data', $reg['json']);
        self::assertArrayHasKey('access_token', $reg['json']['data']);
        self::assertArrayHasKey('refresh_token', $reg['json']['data']);

        $access = (string) $reg['json']['data']['access_token'];
        $refresh = (string) $reg['json']['data']['refresh_token'];

        $me = $this->legacyApiJson('GET', '/api/v1/me', token: $access);
        self::assertSame(200, $me['status']);
        self::assertArrayHasKey('data', $me['json']);
        self::assertSame('auth-buyer@example.test', $me['json']['data']['email']);

        $logout = $this->legacyApiJson('POST', '/api/v1/auth/logout', token: $access);
        self::assertSame(200, $logout['status']);
        self::assertSame(['ok' => true], $logout['json']['data']);

        $meAfterLogout = $this->legacyApiJson('GET', '/api/v1/me', token: $access);
        self::assertSame(401, $meAfterLogout['status']);
        self::assertSame('unauthenticated', $meAfterLogout['json']['error']);

        $login = $this->legacyApiJson('POST', '/api/v1/auth/login', [
            'email' => 'auth-buyer@example.test',
            'password' => 'secret1234',
        ]);
        self::assertSame(200, $login['status']);
        self::assertArrayHasKey('access_token', $login['json']['data']);
        self::assertArrayHasKey('refresh_token', $login['json']['data']);

        $staleRefresh = $this->legacyApiJson('POST', '/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        self::assertSame(401, $staleRefresh['status']);

        // Logout revokes the registration refresh token; login issues a new pair.
        $refreshAfterLogin = (string) $login['json']['data']['refresh_token'];
        $refreshRes = $this->legacyApiJson('POST', '/api/v1/auth/refresh', ['refresh_token' => $refreshAfterLogin]);
        self::assertSame(200, $refreshRes['status']);
        self::assertArrayHasKey('access_token', $refreshRes['json']['data']);

        $badRefresh = $this->legacyApiJson('POST', '/api/v1/auth/refresh', ['refresh_token' => 'rt_invalid']);
        self::assertSame(401, $badRefresh['status']);
        self::assertSame('unauthenticated', $badRefresh['json']['error']);
        self::assertSame('invalid_refresh_token', $badRefresh['json']['reason_code']);
    }

    public function test_me_and_me_seller_endpoints_with_success_and_error_envelopes(): void
    {
        $sellerReg = $this->legacyApiJson('POST', '/api/v1/auth/register', [
            'account_type' => 'seller',
            'email' => 'seller-profile@example.test',
            'password' => 'secret1234',
            'display_name' => 'Seller Display',
            'legal_name' => 'Seller LLC',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $sellerToken = (string) $sellerReg['json']['data']['access_token'];

        $getMe = $this->legacyApiJson('GET', '/api/v1/me', token: $sellerToken);
        self::assertSame(200, $getMe['status']);
        self::assertSame('seller-profile@example.test', $getMe['json']['data']['email']);

        $patchMe = $this->legacyApiJson('PATCH', '/api/v1/me', [
            'phone' => '+15550001111',
        ], $sellerToken);
        self::assertSame(200, $patchMe['status']);
        self::assertSame('+15550001111', $patchMe['json']['data']['phone']);

        $getSeller = $this->legacyApiJson('GET', '/api/v1/me/seller', token: $sellerToken);
        self::assertSame(200, $getSeller['status']);
        self::assertSame('Seller Display', $getSeller['json']['data']['display_name']);

        $patchSeller = $this->legacyApiJson('PATCH', '/api/v1/me/seller', [
            'display_name' => 'Seller Updated',
            'legal_name' => 'Seller Updated LLC',
        ], $sellerToken);
        self::assertSame(200, $patchSeller['status']);
        self::assertSame('Seller Updated', $patchSeller['json']['data']['display_name']);

        $buyerReg = $this->legacyApiJson('POST', '/api/v1/auth/register', [
            'account_type' => 'buyer',
            'email' => 'buyer-no-seller@example.test',
            'password' => 'secret1234',
            'display_name' => 'Buyer No Seller',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $buyerToken = (string) $buyerReg['json']['data']['access_token'];
        $missingSeller = $this->legacyApiJson('GET', '/api/v1/me/seller', token: $buyerToken);
        self::assertSame(404, $missingSeller['status']);
        self::assertSame('not_found', $missingSeller['json']['error']);
        self::assertSame('seller_profile_not_found', $missingSeller['json']['reason_code']);
    }

    public function test_public_catalog_endpoints_with_search_detail_and_pagination(): void
    {
        [$seller, $storefront, $category] = $this->seedCatalogOwner();
        $publishedA = $this->seedProduct($seller, $storefront, $category, 'Widget A', 'published', now()->subMinute());
        $publishedB = $this->seedProduct($seller, $storefront, $category, 'Widget B', 'published', now());
        $this->seedProduct($seller, $storefront, $category, 'Hidden Draft', 'draft', null);

        $list = $this->legacyApiJson('GET', '/api/v1/products?page=1&per_page=1');
        self::assertSame(200, $list['status']);
        self::assertCount(1, $list['json']['data']);
        self::assertSame(1, $list['json']['meta']['page']);
        self::assertSame(1, $list['json']['meta']['per_page']);
        self::assertSame(2, $list['json']['meta']['total']);

        $search = $this->legacyApiJson('GET', '/api/v1/products/search?search=Widget');
        self::assertSame(200, $search['status']);
        self::assertGreaterThanOrEqual(2, count($search['json']['data']));

        $emptySearch = $this->legacyApiJson('GET', '/api/v1/products/search');
        self::assertSame(422, $emptySearch['status']);
        self::assertSame('validation_failed', $emptySearch['json']['error']);
        self::assertSame('search_query_required', $emptySearch['json']['reason_code']);

        $show = $this->legacyApiJson('GET', '/api/v1/products/'.$publishedA->id);
        self::assertSame(200, $show['status']);
        self::assertSame($publishedA->id, $show['json']['data']['id']);

        $notFound = $this->legacyApiJson('GET', '/api/v1/products/999999');
        self::assertSame(404, $notFound['status']);
        self::assertSame('not_found', $notFound['json']['error']);
        self::assertSame('product_not_found', $notFound['json']['reason_code']);

        // keep variable used to satisfy static analyzers in strict environments
        self::assertSame($publishedB->id > 0, true);
    }

    public function test_orders_endpoints_cover_list_detail_policy_and_payment_transitions(): void
    {
        [$buyer, $sellerProfile, $order] = $this->seedOrder(OrderStatus::Draft, '35.0000');
        [, , $order2] = $this->seedOrder(OrderStatus::Draft, '20.0000', $buyer, $sellerProfile);
        $buyerToken = $this->issueAccessTokenForUser($buyer);

        $list = $this->legacyApiJson('GET', '/api/v1/orders?page=1&per_page=1', token: $buyerToken);
        self::assertSame(200, $list['status']);
        self::assertCount(1, $list['json']['data']);
        self::assertSame(1, $list['json']['meta']['per_page']);
        self::assertGreaterThanOrEqual(2, $list['json']['meta']['total']);

        $show = $this->legacyApiJson('GET', '/api/v1/orders/'.$order->id, token: $buyerToken);
        self::assertSame(200, $show['status']);
        self::assertSame($order->id, $show['json']['data']['id']);

        $stranger = $this->createUser('stranger-orders@example.test');
        $strangerToken = $this->issueAccessTokenForUser($stranger);
        $forbidden = $this->legacyApiJson('GET', '/api/v1/orders/'.$order->id, token: $strangerToken);
        self::assertSame(403, $forbidden['status']);
        self::assertSame('forbidden', $forbidden['json']['error']);

        $pending = $this->legacyApiJson('POST', '/api/v1/orders/'.$order->id.'/mark-pending-payment', [], $buyerToken);
        self::assertSame(200, $pending['status']);
        self::assertSame('pending_payment', $pending['json']['data']['status']);

        [$intent, $txn] = $this->seedCapturedPayment($order, '35.0000');
        $paid = $this->legacyApiJson('POST', '/api/v1/orders/'.$order->id.'/mark-paid', [
            'payment_transaction_id' => $txn->id,
        ], $buyerToken);
        self::assertSame(200, $paid['status']);
        self::assertSame('paid_in_escrow', $paid['json']['data']['status']);

        $sellerToken = $this->issueAccessTokenForUser($sellerProfile->user);
        $sellerDenied = $this->legacyApiJson('POST', '/api/v1/orders/'.$order2->id.'/mark-pending-payment', [], $sellerToken);
        self::assertSame(403, $sellerDenied['status']);
        self::assertSame('forbidden', $sellerDenied['json']['error']);

        self::assertSame($intent->id > 0, true);
    }

    public function test_disputes_endpoints_cover_full_http_lifecycle_and_policy_checks(): void
    {
        [$order1, $buyer1] = $this->seedPaidEscrowOrderForDisputes('40.0000');
        $buyerToken = $this->issueAccessTokenForUser($buyer1);
        $admin = $this->createUser('admin-disputes@example.test');
        $this->assignRole($admin, RoleCodes::Admin);
        $adminToken = $this->issueAccessTokenForUser($admin);

        $open = $this->legacyApiJson('POST', '/api/v1/orders/'.$order1->id.'/disputes', [
            'reason_code' => 'item_not_received',
            'idempotency_key' => 'd-open-'.Str::random(8),
        ], $buyerToken);
        self::assertSame(201, $open['status']);
        $caseId = (int) $open['json']['data']['dispute_case_id'];

        $list = $this->legacyApiJson('GET', '/api/v1/disputes?page=1&per_page=10', token: $buyerToken);
        self::assertSame(200, $list['status']);
        self::assertNotEmpty($list['json']['data']);
        self::assertArrayHasKey('meta', $list['json']);

        $show = $this->legacyApiJson('GET', '/api/v1/disputes/'.$caseId, token: $buyerToken);
        self::assertSame(200, $show['status']);
        self::assertSame($caseId, $show['json']['data']['id']);

        $evidence = $this->legacyApiJson('POST', '/api/v1/disputes/'.$caseId.'/evidence', [
            'evidence' => [[
                'evidence_type' => 'text',
                'content_text' => 'Package never arrived',
            ]],
        ], $buyerToken);
        self::assertSame(200, $evidence['status']);
        self::assertSame('evidence_collection', $evidence['json']['data']['status']);

        $toReview = $this->legacyApiJson('POST', '/api/v1/disputes/'.$caseId.'/move-to-review', [], $buyerToken);
        self::assertSame(200, $toReview['status']);
        self::assertSame('under_review', $toReview['json']['data']['status']);

        $escalate = $this->legacyApiJson('POST', '/api/v1/disputes/'.$caseId.'/escalate', [], $buyerToken);
        self::assertSame(200, $escalate['status']);
        self::assertSame('escalated', $escalate['json']['data']['status']);

        $resolveDenied = $this->legacyApiJson('POST', '/api/v1/disputes/'.$caseId.'/resolve/refund', [
            'currency' => 'USD',
            'reason_code' => 'deny',
            'notes' => 'deny',
            'idempotency_key' => 'd-ref-denied-'.Str::random(6),
        ], $buyerToken);
        self::assertSame(403, $resolveDenied['status']);

        $resolveRefund = $this->legacyApiJson('POST', '/api/v1/disputes/'.$caseId.'/resolve/refund', [
            'currency' => 'USD',
            'reason_code' => 'buyer_wins',
            'notes' => 'full refund',
            'idempotency_key' => 'd-ref-'.Str::random(8),
        ], $adminToken);
        self::assertSame(200, $resolveRefund['status']);

        [$order2, $buyer2] = $this->seedPaidEscrowOrderForDisputes('35.0000');
        $buyer2Token = $this->issueAccessTokenForUser($buyer2);
        $case2 = (int) $this->legacyApiJson('POST', '/api/v1/orders/'.$order2->id.'/disputes', [
            'reason_code' => 'seller_should_win',
            'idempotency_key' => 'd-open-2-'.Str::random(6),
        ], $buyer2Token)['json']['data']['dispute_case_id'];
        $this->legacyApiJson('POST', '/api/v1/disputes/'.$case2.'/move-to-review', [], $buyer2Token);
        $resolveRelease = $this->legacyApiJson('POST', '/api/v1/disputes/'.$case2.'/resolve/release', [
            'currency' => 'USD',
            'reason_code' => 'seller_wins',
            'notes' => 'release escrow',
            'idempotency_key' => 'd-rel-'.Str::random(8),
        ], $adminToken);
        self::assertSame(200, $resolveRelease['status']);

        [$order3, $buyer3] = $this->seedPaidEscrowOrderForDisputes('60.0000');
        $buyer3Token = $this->issueAccessTokenForUser($buyer3);
        $case3 = (int) $this->legacyApiJson('POST', '/api/v1/orders/'.$order3->id.'/disputes', [
            'reason_code' => 'split_case',
            'idempotency_key' => 'd-open-3-'.Str::random(6),
        ], $buyer3Token)['json']['data']['dispute_case_id'];
        $this->legacyApiJson('POST', '/api/v1/disputes/'.$case3.'/move-to-review', [], $buyer3Token);
        $resolveSplit = $this->legacyApiJson('POST', '/api/v1/disputes/'.$case3.'/resolve/split', [
            'buyer_refund_amount' => '20.0000',
            'currency' => 'USD',
            'reason_code' => 'split',
            'notes' => 'partial refund',
            'idempotency_key' => 'd-split-'.Str::random(8),
        ], $adminToken);
        self::assertSame(200, $resolveSplit['status']);
    }

    public function test_withdrawal_endpoints_cover_listing_details_request_review_approve_reject_and_policy(): void
    {
        [$sellerUser, $sellerProfile, $sellerWalletId] = $this->seedSellerWithFundedWallet('300.0000');
        $sellerToken = $this->issueAccessTokenForUser($sellerUser);
        $admin = $this->createUser('admin-withdrawals@example.test');
        $this->assignRole($admin, RoleCodes::Admin);
        $adminToken = $this->issueAccessTokenForUser($admin);

        $requestA = $this->legacyApiJson('POST', '/api/v1/withdrawals', [
            'seller_profile_id' => $sellerProfile->id,
            'wallet_id' => $sellerWalletId,
            'amount' => '50.0000',
            'currency' => 'USD',
            'idempotency_key' => 'wd-a-'.Str::random(8),
        ], $sellerToken);
        self::assertSame(201, $requestA['status']);
        $wrA = (int) $requestA['json']['data']['withdrawal_request_id'];

        $list = $this->legacyApiJson('GET', '/api/v1/withdrawals?page=1&per_page=10', token: $sellerToken);
        self::assertSame(200, $list['status']);
        self::assertArrayHasKey('meta', $list['json']);
        self::assertNotEmpty($list['json']['data']);

        $show = $this->legacyApiJson('GET', '/api/v1/withdrawals/'.$wrA, token: $sellerToken);
        self::assertSame(200, $show['status']);
        self::assertSame($wrA, $show['json']['data']['id']);

        $approveDenied = $this->legacyApiJson('POST', '/api/v1/withdrawals/'.$wrA.'/approve', [
            'idempotency_key' => 'wd-denied-'.Str::random(6),
        ], $sellerToken);
        self::assertSame(403, $approveDenied['status']);
        self::assertSame('forbidden', $approveDenied['json']['error']);

        $review = $this->legacyApiJson('POST', '/api/v1/withdrawals/'.$wrA.'/review', [
            'decision' => 'approved',
            'idempotency_key' => 'wd-review-'.Str::random(8),
        ], $adminToken);
        self::assertSame(200, $review['status']);
        self::assertSame('paid_out', $review['json']['data']['status']);

        $requestB = $this->legacyApiJson('POST', '/api/v1/withdrawals', [
            'seller_profile_id' => $sellerProfile->id,
            'wallet_id' => $sellerWalletId,
            'amount' => '25.0000',
            'currency' => 'USD',
            'idempotency_key' => 'wd-b-'.Str::random(8),
        ], $sellerToken);
        self::assertSame(201, $requestB['status']);
        $wrB = (int) $requestB['json']['data']['withdrawal_request_id'];
        $approve = $this->legacyApiJson('POST', '/api/v1/withdrawals/'.$wrB.'/approve', [
            'idempotency_key' => 'wd-approve-'.Str::random(8),
        ], $adminToken);
        self::assertSame(200, $approve['status']);
        self::assertSame('paid_out', $approve['json']['data']['status']);

        $requestC = $this->legacyApiJson('POST', '/api/v1/withdrawals', [
            'seller_profile_id' => $sellerProfile->id,
            'wallet_id' => $sellerWalletId,
            'amount' => '10.0000',
            'currency' => 'USD',
            'idempotency_key' => 'wd-c-'.Str::random(8),
        ], $sellerToken);
        $wrC = (int) $requestC['json']['data']['withdrawal_request_id'];
        $reject = $this->legacyApiJson('POST', '/api/v1/withdrawals/'.$wrC.'/reject', [
            'idempotency_key' => 'wd-reject-'.Str::random(8),
            'reason' => 'manual review reject',
        ], $adminToken);
        self::assertSame(200, $reject['status']);
        self::assertSame('rejected', $reject['json']['data']['status']);
    }

    public function test_profile_extras_endpoints_cover_payment_methods_wishlist_and_reviews(): void
    {
        [$seller, $storefront, $category] = $this->seedCatalogOwner();
        $product = $this->seedProduct($seller, $storefront, $category, 'Buyer Review Product', 'published', now());
        $buyer = $this->createUser('buyer-extras-'.Str::random(6).'@example.test');

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.Str::upper(Str::random(10)),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::Completed,
            'currency' => 'USD',
            'gross_amount' => '20.0000',
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => '20.0000',
            'placed_at' => now(),
        ]);

        $orderItem = OrderItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'seller_profile_id' => $seller->id,
            'product_id' => $product->id,
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Buyer Review Product',
            'sku_snapshot' => 'SKU-'.Str::upper(Str::random(6)),
            'quantity' => 1,
            'unit_price_snapshot' => '20.0000',
            'line_total_snapshot' => '20.0000',
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'delivered',
        ]);

        \App\Models\Review::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_item_id' => $orderItem->id,
            'buyer_user_id' => $buyer->id,
            'seller_profile_id' => $seller->id,
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'Great product.',
            'status' => 'visible',
        ]);

        $buyerToken = $this->issueAccessTokenForUser($buyer);

        $initialMethods = $this->legacyApiJson('GET', '/api/v1/me/payment-methods', token: $buyerToken);
        self::assertSame(200, $initialMethods['status']);
        self::assertIsArray($initialMethods['json']['data']);

        $createCard = $this->legacyApiJson('POST', '/api/v1/me/payment-methods', [
            'kind' => 'card',
            'label' => 'Visa **** 1111',
            'subtitle' => 'Expires 08/30',
            'is_default' => true,
        ], $buyerToken);
        self::assertSame(201, $createCard['status']);
        self::assertSame('card', $createCard['json']['data']['kind']);
        $createdMethodId = (int) $createCard['json']['data']['id'];

        $createBkash = $this->legacyApiJson('POST', '/api/v1/me/payment-methods', [
            'kind' => 'bkash',
            'label' => 'bKash 01XXXXXXXX',
            'subtitle' => 'Mobile wallet',
            'is_default' => false,
        ], $buyerToken);
        self::assertSame(201, $createBkash['status']);
        $bkashMethodId = (int) $createBkash['json']['data']['id'];

        $setDefault = $this->legacyApiJson('PATCH', '/api/v1/me/payment-methods/'.$bkashMethodId, [], $buyerToken);
        self::assertSame(200, $setDefault['status']);
        self::assertTrue((bool) $setDefault['json']['data']['is_default']);

        $listMethods = $this->legacyApiJson('GET', '/api/v1/me/payment-methods', token: $buyerToken);
        self::assertSame(200, $listMethods['status']);
        self::assertGreaterThanOrEqual(2, count($listMethods['json']['data']));

        $deleteMethod = $this->legacyApiJson('DELETE', '/api/v1/me/payment-methods/'.$createdMethodId, token: $buyerToken);
        self::assertSame(200, $deleteMethod['status']);
        self::assertSame(['ok' => true], $deleteMethod['json']['data']);

        $wishlistInitial = $this->legacyApiJson('GET', '/api/v1/me/wishlist', token: $buyerToken);
        self::assertSame(200, $wishlistInitial['status']);
        self::assertIsArray($wishlistInitial['json']['data']);

        $addWishlist = $this->legacyApiJson('POST', '/api/v1/me/wishlist', [
            'product_id' => $product->id,
        ], $buyerToken);
        self::assertSame(201, $addWishlist['status']);
        self::assertSame($product->id, $addWishlist['json']['data']['product_id']);

        $wishlistAfterAdd = $this->legacyApiJson('GET', '/api/v1/me/wishlist', token: $buyerToken);
        self::assertSame(200, $wishlistAfterAdd['status']);
        self::assertNotEmpty($wishlistAfterAdd['json']['data']);

        $removeWishlist = $this->legacyApiJson('DELETE', '/api/v1/me/wishlist/'.$product->id, token: $buyerToken);
        self::assertSame(200, $removeWishlist['status']);
        self::assertSame(['ok' => true], $removeWishlist['json']['data']);

        $reviews = $this->legacyApiJson('GET', '/api/v1/me/reviews', token: $buyerToken);
        self::assertSame(200, $reviews['status']);
        self::assertNotEmpty($reviews['json']['data']);
        self::assertSame(5, $reviews['json']['data'][0]['rating']);
        self::assertSame('Great product.', $reviews['json']['data'][0]['comment']);

        self::assertSame(0, UserWishlistItem::query()->where('user_id', $buyer->id)->where('product_id', $product->id)->count());
        self::assertGreaterThanOrEqual(1, UserPaymentMethod::query()->where('user_id', $buyer->id)->count());
    }

    public function test_profile_extras_endpoints_cover_auth_and_edge_cases(): void
    {
        [$seller, $storefront, $category] = $this->seedCatalogOwner();
        $product = $this->seedProduct($seller, $storefront, $category, 'Edge Product', 'published', now());

        $owner = $this->createUser('buyer-extras-owner-'.Str::random(6).'@example.test');
        $stranger = $this->createUser('buyer-extras-stranger-'.Str::random(6).'@example.test');
        $ownerToken = $this->issueAccessTokenForUser($owner);
        $strangerToken = $this->issueAccessTokenForUser($stranger);

        $unauthPaymentMethods = $this->legacyApiJson('GET', '/api/v1/me/payment-methods');
        self::assertSame(401, $unauthPaymentMethods['status']);
        self::assertSame('unauthenticated', $unauthPaymentMethods['json']['error']);

        $unauthReviews = $this->legacyApiJson('GET', '/api/v1/me/reviews');
        self::assertSame(401, $unauthReviews['status']);
        self::assertSame('unauthenticated', $unauthReviews['json']['error']);

        $ownerMethod = $this->legacyApiJson('POST', '/api/v1/me/payment-methods', [
            'kind' => 'card',
            'label' => 'Owner Card',
            'subtitle' => 'Test',
            'is_default' => true,
        ], $ownerToken);
        self::assertSame(201, $ownerMethod['status']);
        $ownerMethodId = (int) $ownerMethod['json']['data']['id'];

        $strangerSetDefault = $this->legacyApiJson('PATCH', '/api/v1/me/payment-methods/'.$ownerMethodId, [], $strangerToken);
        self::assertSame(404, $strangerSetDefault['status']);
        self::assertSame('not_found', $strangerSetDefault['json']['error']);
        self::assertSame('not_found', $strangerSetDefault['json']['reason_code']);

        $strangerDelete = $this->legacyApiJson('DELETE', '/api/v1/me/payment-methods/'.$ownerMethodId, token: $strangerToken);
        self::assertSame(200, $strangerDelete['status']);
        self::assertSame(['ok' => true], $strangerDelete['json']['data']);
        self::assertGreaterThan(0, UserPaymentMethod::query()->whereKey($ownerMethodId)->count());

        $missingProductAdd = $this->legacyApiJson('POST', '/api/v1/me/wishlist', [
            'product_id' => 99999999,
        ], $ownerToken);
        self::assertSame(404, $missingProductAdd['status']);
        self::assertSame('not_found', $missingProductAdd['json']['error']);
        self::assertSame('product_not_found', $missingProductAdd['json']['reason_code']);

        $firstAdd = $this->legacyApiJson('POST', '/api/v1/me/wishlist', [
            'product_id' => $product->id,
        ], $ownerToken);
        self::assertSame(201, $firstAdd['status']);
        $wishlistId = (int) $firstAdd['json']['data']['id'];

        $duplicateAdd = $this->legacyApiJson('POST', '/api/v1/me/wishlist', [
            'product_id' => $product->id,
        ], $ownerToken);
        self::assertSame(201, $duplicateAdd['status']);
        self::assertSame($wishlistId, (int) $duplicateAdd['json']['data']['id']);
        self::assertSame(1, UserWishlistItem::query()->where('user_id', $owner->id)->where('product_id', $product->id)->count());

        $strangerRemove = $this->legacyApiJson('DELETE', '/api/v1/me/wishlist/'.$product->id, token: $strangerToken);
        self::assertSame(200, $strangerRemove['status']);
        self::assertSame(['ok' => true], $strangerRemove['json']['data']);
        self::assertSame(1, UserWishlistItem::query()->where('user_id', $owner->id)->where('product_id', $product->id)->count());
    }

    public function test_profile_notification_center_endpoints_cover_list_read_and_preferences(): void
    {
        $user = $this->createUser('buyer-notification-'.Str::random(6).'@example.test');
        $token = $this->issueAccessTokenForUser($user);

        $first = Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'channel' => 'in_app',
            'template_code' => 'order_update',
            'payload_json' => ['title' => 'Order shipped', 'body' => 'Your order is on the way'],
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);
        Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'channel' => 'in_app',
            'template_code' => 'promo',
            'payload_json' => ['title' => 'New promo', 'body' => 'Save 20% today'],
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);

        $list = $this->legacyApiJson('GET', '/api/v1/me/notifications', token: $token);
        self::assertSame(200, $list['status']);
        self::assertSame(2, $list['json']['data']['unread_count']);
        self::assertCount(2, $list['json']['data']['items']);

        $markOne = $this->legacyApiJson('PATCH', '/api/v1/me/notifications/'.$first->id.'/read', [], $token);
        self::assertSame(200, $markOne['status']);
        self::assertTrue((bool) $markOne['json']['data']['is_read']);

        $markAll = $this->legacyApiJson('POST', '/api/v1/me/notifications/read-all', [], $token);
        self::assertSame(200, $markAll['status']);
        self::assertSame(1, (int) $markAll['json']['data']['updated']);

        $after = $this->legacyApiJson('GET', '/api/v1/me/notifications', token: $token);
        self::assertSame(200, $after['status']);
        self::assertSame(0, $after['json']['data']['unread_count']);

        $prefGet = $this->legacyApiJson('GET', '/api/v1/me/notifications/preferences', token: $token);
        self::assertSame(200, $prefGet['status']);
        self::assertTrue((bool) $prefGet['json']['data']['in_app_enabled']);

        $prefPatch = $this->legacyApiJson('PATCH', '/api/v1/me/notifications/preferences', [
            'email_enabled' => false,
            'promotion_enabled' => false,
        ], $token);
        self::assertSame(200, $prefPatch['status']);
        self::assertFalse((bool) $prefPatch['json']['data']['email_enabled']);
        self::assertFalse((bool) $prefPatch['json']['data']['promotion_enabled']);

    }

    public function test_chat_endpoints_cover_order_thread_messages_and_support_ticket(): void
    {
        [$buyer, $sellerProfile, $order] = $this->seedOrder(OrderStatus::Draft, '35.0000');
        $buyerToken = $this->issueAccessTokenForUser($buyer);
        $sellerToken = $this->issueAccessTokenForUser($sellerProfile->user);

        $thread = $this->legacyApiJson('POST', '/api/v1/orders/'.$order->id.'/chat-thread', [], $buyerToken);
        self::assertSame(200, $thread['status']);
        $threadId = (int) $thread['json']['data']['thread_id'];
        self::assertGreaterThan(0, $threadId);

        $sendBuyer = $this->legacyApiJson('POST', '/api/v1/chat/threads/'.$threadId.'/messages', [
            'body' => 'Hello seller',
        ], $buyerToken);
        self::assertSame(201, $sendBuyer['status']);
        self::assertTrue((bool) $sendBuyer['json']['data']['from_me']);

        $sellerList = $this->legacyApiJson('GET', '/api/v1/chat/threads', token: $sellerToken);
        self::assertSame(200, $sellerList['status']);
        self::assertNotEmpty($sellerList['json']['data']['items']);
        self::assertSame(1, $sellerList['json']['data']['unread_count']);

        $sellerMessages = $this->legacyApiJson('GET', '/api/v1/chat/threads/'.$threadId.'/messages', token: $sellerToken);
        self::assertSame(200, $sellerMessages['status']);
        self::assertNotEmpty($sellerMessages['json']['data']);
        self::assertSame('sent', $sellerMessages['json']['data'][0]['delivery_status']);

        $typingOn = $this->legacyApiJson('POST', '/api/v1/chat/threads/'.$threadId.'/typing', ['typing' => true], $buyerToken);
        self::assertSame(200, $typingOn['status']);
        $typingStatus = $this->legacyApiJson('GET', '/api/v1/chat/threads/'.$threadId.'/typing', token: $sellerToken);
        self::assertSame(200, $typingStatus['status']);
        self::assertNotEmpty($typingStatus['json']['data']);

        $markRead = $this->legacyApiJson('POST', '/api/v1/chat/threads/'.$threadId.'/read', [], $sellerToken);
        self::assertSame(200, $markRead['status']);
        self::assertSame(['ok' => true], $markRead['json']['data']);

        $sellerListAfterRead = $this->legacyApiJson('GET', '/api/v1/chat/threads', token: $sellerToken);
        self::assertSame(200, $sellerListAfterRead['status']);
        self::assertSame(0, $sellerListAfterRead['json']['data']['unread_count']);

        $support = $this->legacyApiJson('POST', '/api/v1/chat/support-tickets', [
            'subject' => 'Need help',
            'message' => 'Please check my order issue',
        ], $buyerToken);
        self::assertSame(201, $support['status']);
        self::assertGreaterThan(0, (int) $support['json']['data']['thread_id']);

        $supportAgent = $this->createUser('support-agent-'.Str::random(6).'@example.test');
        $this->assignRole($supportAgent, RoleCodes::SupportAgent);
        $supportToken = $this->issueAccessTokenForUser($supportAgent);
        $supportInbox = $this->legacyApiJson('GET', '/api/v1/chat/support-inbox', token: $supportToken);
        self::assertSame(200, $supportInbox['status']);
        self::assertNotEmpty($supportInbox['json']['data']);
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function legacyApiJson(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $content = in_array(strtoupper($method), ['GET', 'HEAD'], true) ? '' : (string) json_encode($body);
        $request = Request::create($path, strtoupper($method), [], [], [], $server, $content);
        $response = $this->kernel->handle($request);
        $decoded = json_decode($response->getContent(), true);
        self::assertIsArray($decoded, 'JSON response expected');

        return [
            'status' => $response->getStatusCode(),
            'json' => $decoded,
        ];
    }

    private function createUser(string $email, string $password = 'secret1234'): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'risk_level' => 'low',
        ]);
    }

    private function issueAccessTokenForUser(User $user): string
    {
        $plain = 'at_'.Str::random(48);
        UserAuthToken::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'token_family' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $plain),
            'kind' => UserAuthToken::KIND_ACCESS,
            'expires_at' => now()->addHour(),
            'revoked_at' => null,
            'created_at' => now(),
        ]);

        return $plain;
    }

    private function assignRole(User $user, string $roleCode): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => ucfirst($roleCode)]
        );
        UserRole::query()->firstOrCreate(
            ['user_id' => $user->id, 'role_id' => $role->id],
            ['assigned_by' => null]
        );
    }

    /**
     * @return array{0: SellerProfile, 1: Storefront, 2: Category}
     */
    private function seedCatalogOwner(): array
    {
        $sellerUser = $this->createUser('catalog-seller-'.Str::random(6).'@example.test');
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Catalog Seller',
            'legal_name' => 'Catalog Seller LLC',
            'country_code' => 'US',
            'default_currency' => 'USD',
            'verification_status' => 'unverified',
            'store_status' => 'active',
        ]);
        $storefront = Storefront::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'slug' => 'catalog-'.Str::lower(Str::random(8)),
            'title' => 'Catalog Store',
            'description' => 'Store for catalog tests',
            'policy_text' => null,
            'is_public' => true,
        ]);
        $category = Category::query()->create([
            'parent_id' => null,
            'slug' => 'cat-'.Str::lower(Str::random(8)),
            'name' => 'Category',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$seller, $storefront, $category];
    }

    private function seedProduct(SellerProfile $seller, Storefront $storefront, Category $category, string $title, string $status, mixed $publishedAt): Product
    {
        return Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $seller->id,
            'storefront_id' => $storefront->id,
            'category_id' => $category->id,
            'product_type' => 'physical',
            'title' => $title,
            'description' => $title.' description',
            'base_price' => '12.0000',
            'currency' => 'USD',
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }

    /**
     * @return array{0: User, 1: SellerProfile, 2: Order}
     */
    private function seedOrder(OrderStatus $status, string $netAmount, ?User $buyer = null, ?SellerProfile $sellerProfile = null): array
    {
        $buyer ??= $this->createUser('buyer-'.Str::random(8).'@example.test');
        if ($sellerProfile === null) {
            $sellerUser = $this->createUser('seller-'.Str::random(8).'@example.test');
            $sellerProfile = SellerProfile::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $sellerUser->id,
                'display_name' => 'Seller',
                'legal_name' => 'Seller LLC',
                'country_code' => 'US',
                'default_currency' => 'USD',
                'verification_status' => 'unverified',
                'store_status' => 'active',
            ]);
        }

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.Str::upper(Str::random(10)),
            'buyer_user_id' => $buyer->id,
            'status' => $status,
            'currency' => 'USD',
            'gross_amount' => $netAmount,
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => $netAmount,
            'placed_at' => $status === OrderStatus::PaidInEscrow ? now() : null,
        ]);

        OrderItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'seller_profile_id' => $sellerProfile->id,
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Item',
            'sku_snapshot' => 'SKU-'.Str::upper(Str::random(6)),
            'quantity' => 1,
            'unit_price_snapshot' => $netAmount,
            'line_total_snapshot' => $netAmount,
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ]);

        $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $buyer->id,
            walletType: WalletType::Buyer,
            currency: 'USD',
        ));
        $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $sellerProfile->user_id,
            walletType: WalletType::Seller,
            currency: 'USD',
        ));

        return [$buyer, $sellerProfile, $order];
    }

    /**
     * @return array{0: PaymentIntent, 1: PaymentTransaction}
     */
    private function seedCapturedPayment(Order $order, string $amount): array
    {
        $intent = PaymentIntent::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'provider' => 'test',
            'provider_intent_ref' => 'pi_'.Str::random(12),
            'status' => 'captured',
            'amount' => $amount,
            'currency' => (string) $order->currency,
            'expires_at' => null,
        ]);

        $txn = PaymentTransaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'payment_intent_id' => $intent->id,
            'order_id' => $order->id,
            'provider_txn_ref' => 'txn_'.Str::random(12),
            'txn_type' => 'capture',
            'status' => 'success',
            'amount' => $amount,
            'raw_payload_json' => [],
            'processed_at' => now(),
        ]);

        return [$intent, $txn];
    }

    /**
     * @return array{0: Order, 1: User}
     */
    private function seedPaidEscrowOrderForDisputes(string $amount): array
    {
        [$buyer, $sellerProfile, $order] = $this->seedOrder(OrderStatus::PaidInEscrow, $amount);

        $buyerWalletId = (int) $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $buyer->id,
            walletType: WalletType::Buyer,
            currency: 'USD',
        ))['wallet_id'];

        $this->wallet->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'seed',
            referenceId: $order->id,
            idempotencyKey: 'seed-dispute-order-'.$order->id,
            entries: [
                new \App\Domain\Value\LedgerPostingLine(
                    walletId: $buyerWalletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: '200.0000',
                    currency: 'USD',
                    referenceType: 'seed',
                    referenceId: $order->id,
                    counterpartyWalletId: null,
                    description: 'seed_buyer',
                ),
            ],
        ));

        $create = $this->escrow->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $order->id,
            currency: 'USD',
            heldAmount: $amount,
            idempotencyKey: 'dispute-escrow-create-'.$order->id,
        ));

        $this->escrow->holdEscrow(new HoldEscrowCommand(
            escrowAccountId: (int) $create['escrow_account_id'],
            idempotencyKey: 'dispute-escrow-hold-'.$order->id,
        ));

        return [$order, $buyer];
    }

    /**
     * @return array{0: User, 1: SellerProfile, 2: int}
     */
    private function seedSellerWithFundedWallet(string $depositAmount): array
    {
        $sellerUser = $this->createUser('seller-wd-'.Str::random(8).'@example.test');
        $seller = SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUser->id,
            'display_name' => 'Seller WD',
            'legal_name' => 'Seller WD LLC',
            'country_code' => 'US',
            'default_currency' => 'USD',
            'verification_status' => 'unverified',
            'store_status' => 'active',
        ]);

        $walletId = (int) $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $sellerUser->id,
            walletType: WalletType::Seller,
            currency: 'USD',
        ))['wallet_id'];

        $this->wallet->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'seed',
            referenceId: $seller->id,
            idempotencyKey: 'seed-wd-'.$seller->id.'-'.Str::random(6),
            entries: [
                new \App\Domain\Value\LedgerPostingLine(
                    walletId: $walletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: $depositAmount,
                    currency: 'USD',
                    referenceType: 'seed',
                    referenceId: $seller->id,
                    counterpartyWalletId: null,
                    description: 'seed_seller',
                ),
            ],
        ));

        return [$sellerUser, $seller, $walletId];
    }
}


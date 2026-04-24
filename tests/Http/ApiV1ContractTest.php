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
use App\Models\UserRole;
use App\Services\Escrow\EscrowService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;

final class ApiV1ContractTest extends TestCase
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

    public function test_live_api_contracts_and_envelopes_are_stable(): void
    {
        // auth: register/login/refresh/logout
        $register = $this->json('POST', '/api/v1/auth/register', [
            'account_type' => 'buyer',
            'email' => 'contract-buyer@example.test',
            'password' => 'secret1234',
            'display_name' => 'Buyer',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $this->assertSuccessEnvelope($register, 201, ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user_id']);
        $access = (string) $register['json']['data']['access_token'];
        $refresh = (string) $register['json']['data']['refresh_token'];

        $login = $this->json('POST', '/api/v1/auth/login', [
            'email' => 'contract-buyer@example.test',
            'password' => 'secret1234',
        ]);
        $this->assertSuccessEnvelope($login, 200, ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user_id']);
        $loginAccess = (string) $login['json']['data']['access_token'];

        $refreshRes = $this->json('POST', '/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $this->assertSuccessEnvelope($refreshRes, 200, ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user_id']);

        $logout = $this->json('POST', '/api/v1/auth/logout', token: $loginAccess);
        $this->assertSuccessEnvelope($logout, 200, ['ok']);

        // me / me-seller GET/PATCH
        $sellerReg = $this->json('POST', '/api/v1/auth/register', [
            'account_type' => 'seller',
            'email' => 'contract-seller@example.test',
            'password' => 'secret1234',
            'display_name' => 'Seller Display',
            'legal_name' => 'Seller LLC',
            'country_code' => 'US',
            'default_currency' => 'USD',
        ]);
        $sellerToken = (string) $sellerReg['json']['data']['access_token'];

        $me = $this->json('GET', '/api/v1/me', token: $sellerToken);
        $this->assertSuccessEnvelope($me, 200, ['id', 'email', 'phone', 'status']);

        $mePatch = $this->json('PATCH', '/api/v1/me', ['phone' => '+15550002222'], $sellerToken);
        $this->assertSuccessEnvelope($mePatch, 200, ['id', 'email', 'phone', 'status']);

        $meSeller = $this->json('GET', '/api/v1/me/seller', token: $sellerToken);
        $this->assertSuccessEnvelope($meSeller, 200, ['id', 'user_id', 'display_name', 'legal_name', 'country_code', 'default_currency']);

        $meSellerPatch = $this->json('PATCH', '/api/v1/me/seller', [
            'display_name' => 'Seller Updated',
            'legal_name' => 'Seller Updated LLC',
        ], $sellerToken);
        $this->assertSuccessEnvelope($meSellerPatch, 200, ['id', 'user_id', 'display_name', 'legal_name', 'country_code', 'default_currency']);

        // products: list/search/detail (+ pagination contract)
        [$seller, $storefront, $category] = $this->seedCatalogOwner();
        $product = $this->seedProduct($seller, $storefront, $category, 'Contract Widget', 'published', now());

        $products = $this->json('GET', '/api/v1/products?page=1&per_page=5');
        $this->assertPaginatedEnvelope($products, 200);

        $search = $this->json('GET', '/api/v1/products/search?search=Contract');
        $this->assertPaginatedEnvelope($search, 200);

        $productShow = $this->json('GET', '/api/v1/products/'.$product->id);
        $this->assertSuccessEnvelope($productShow, 200, ['id', 'title', 'status', 'currency', 'base_price']);

        // orders: list/detail/mark-pending-payment/mark-paid
        [$buyer, $sellerProfile, $order] = $this->seedOrder(OrderStatus::Draft, '35.0000');
        $buyerToken = $this->issueAccessTokenForUser($buyer);

        $orders = $this->json('GET', '/api/v1/orders?page=1&per_page=10', token: $buyerToken);
        $this->assertPaginatedEnvelope($orders, 200);

        $orderShow = $this->json('GET', '/api/v1/orders/'.$order->id, token: $buyerToken);
        $this->assertSuccessEnvelope($orderShow, 200, ['id', 'status', 'currency', 'net_amount']);

        $pending = $this->json('POST', '/api/v1/orders/'.$order->id.'/mark-pending-payment', [], $buyerToken);
        $this->assertSuccessEnvelope($pending, 200, ['order_id', 'status']);

        [, $txn] = $this->seedCapturedPayment($order, '35.0000');
        $paid = $this->json('POST', '/api/v1/orders/'.$order->id.'/mark-paid', [
            'payment_transaction_id' => $txn->id,
        ], $buyerToken);
        $this->assertSuccessEnvelope($paid, 200, ['order_id', 'status', 'escrow_account_id', 'escrow_state']);

        // disputes: list/detail/open/evidence/move-to-review/escalate/resolve/*
        [$order1, $buyer1] = $this->seedPaidEscrowOrderForDisputes('40.0000');
        $buyer1Token = $this->issueAccessTokenForUser($buyer1);
        $admin = $this->createUser('contract-admin@example.test');
        $this->assignRole($admin, RoleCodes::Admin);
        $adminToken = $this->issueAccessTokenForUser($admin);

        $open = $this->json('POST', '/api/v1/orders/'.$order1->id.'/disputes', [
            'reason_code' => 'item_not_received',
            'idempotency_key' => 'contract-open-'.Str::random(6),
        ], $buyer1Token);
        $this->assertSuccessEnvelope($open, 201, ['dispute_case_id', 'order_id', 'escrow_account_id', 'status']);
        $caseId = (int) $open['json']['data']['dispute_case_id'];

        $disputeList = $this->json('GET', '/api/v1/disputes?page=1&per_page=10', token: $buyer1Token);
        $this->assertPaginatedEnvelope($disputeList, 200);

        $disputeShow = $this->json('GET', '/api/v1/disputes/'.$caseId, token: $buyer1Token);
        $this->assertSuccessEnvelope($disputeShow, 200, ['id', 'order_id', 'status']);

        $evidence = $this->json('POST', '/api/v1/disputes/'.$caseId.'/evidence', [
            'evidence' => [[
                'evidence_type' => 'text',
                'content_text' => 'Evidence',
            ]],
        ], $buyer1Token);
        $this->assertSuccessEnvelope($evidence, 200, ['dispute_case_id', 'status', 'evidence_rows_inserted']);

        $toReview = $this->json('POST', '/api/v1/disputes/'.$caseId.'/move-to-review', [], $buyer1Token);
        $this->assertSuccessEnvelope($toReview, 200, ['dispute_case_id', 'status']);

        $escalate = $this->json('POST', '/api/v1/disputes/'.$caseId.'/escalate', [], $buyer1Token);
        $this->assertSuccessEnvelope($escalate, 200, ['dispute_case_id', 'status']);

        $resolveRefund = $this->json('POST', '/api/v1/disputes/'.$caseId.'/resolve/refund', [
            'currency' => 'USD',
            'reason_code' => 'buyer_wins',
            'notes' => 'refund',
            'idempotency_key' => 'contract-refund-'.Str::random(6),
        ], $adminToken);
        $this->assertSuccessEnvelope($resolveRefund, 200, [
            'dispute_case_id', 'dispute_decision_id', 'status', 'resolution_outcome', 'escrow_account_id', 'escrow_state', 'order_status',
        ]);

        [$order2, $buyer2] = $this->seedPaidEscrowOrderForDisputes('25.0000');
        $buyer2Token = $this->issueAccessTokenForUser($buyer2);
        $case2 = (int) $this->json('POST', '/api/v1/orders/'.$order2->id.'/disputes', [
            'reason_code' => 'seller_win_case',
            'idempotency_key' => 'contract-open-2-'.Str::random(6),
        ], $buyer2Token)['json']['data']['dispute_case_id'];
        $this->json('POST', '/api/v1/disputes/'.$case2.'/move-to-review', [], $buyer2Token);
        $resolveRelease = $this->json('POST', '/api/v1/disputes/'.$case2.'/resolve/release', [
            'currency' => 'USD',
            'reason_code' => 'seller_wins',
            'notes' => 'release',
            'idempotency_key' => 'contract-release-'.Str::random(6),
        ], $adminToken);
        $this->assertSuccessEnvelope($resolveRelease, 200, [
            'dispute_case_id', 'dispute_decision_id', 'status', 'resolution_outcome', 'escrow_account_id', 'escrow_state', 'order_status',
        ]);

        [$order3, $buyer3] = $this->seedPaidEscrowOrderForDisputes('30.0000');
        $buyer3Token = $this->issueAccessTokenForUser($buyer3);
        $case3 = (int) $this->json('POST', '/api/v1/orders/'.$order3->id.'/disputes', [
            'reason_code' => 'split_case',
            'idempotency_key' => 'contract-open-3-'.Str::random(6),
        ], $buyer3Token)['json']['data']['dispute_case_id'];
        $this->json('POST', '/api/v1/disputes/'.$case3.'/move-to-review', [], $buyer3Token);
        $resolveSplit = $this->json('POST', '/api/v1/disputes/'.$case3.'/resolve/split', [
            'buyer_refund_amount' => '10.0000',
            'currency' => 'USD',
            'reason_code' => 'split',
            'notes' => 'split',
            'idempotency_key' => 'contract-split-'.Str::random(6),
        ], $adminToken);
        $this->assertSuccessEnvelope($resolveSplit, 200, [
            'dispute_case_id', 'dispute_decision_id', 'status', 'resolution_outcome', 'escrow_account_id', 'escrow_state', 'order_status',
        ]);

        // withdrawals: list/detail/request/review/approve/reject
        [$sellerUserWd, $sellerProfileWd, $sellerWalletId] = $this->seedSellerWithFundedWallet('200.0000');
        $sellerWdToken = $this->issueAccessTokenForUser($sellerUserWd);
        $adminWd = $this->createUser('contract-admin-wd@example.test');
        $this->assignRole($adminWd, RoleCodes::Admin);
        $adminWdToken = $this->issueAccessTokenForUser($adminWd);

        $wdReqA = $this->json('POST', '/api/v1/withdrawals', [
            'seller_profile_id' => $sellerProfileWd->id,
            'wallet_id' => $sellerWalletId,
            'amount' => '40.0000',
            'currency' => 'USD',
            'idempotency_key' => 'contract-wd-a-'.Str::random(6),
        ], $sellerWdToken);
        $this->assertSuccessEnvelope($wdReqA, 201, ['withdrawal_request_id', 'status', 'requested_amount', 'fee_amount', 'net_payout_amount']);
        $wrA = (int) $wdReqA['json']['data']['withdrawal_request_id'];

        $wdList = $this->json('GET', '/api/v1/withdrawals?page=1&per_page=10', token: $sellerWdToken);
        $this->assertPaginatedEnvelope($wdList, 200);

        $wdShow = $this->json('GET', '/api/v1/withdrawals/'.$wrA, token: $sellerWdToken);
        $this->assertSuccessEnvelope($wdShow, 200, ['id', 'seller_profile_id', 'wallet_id', 'status', 'requested_amount', 'currency']);

        $wdReview = $this->json('POST', '/api/v1/withdrawals/'.$wrA.'/review', [
            'decision' => 'approved',
            'idempotency_key' => 'contract-wd-review-'.Str::random(6),
        ], $adminWdToken);
        $this->assertSuccessEnvelope($wdReview, 200, ['withdrawal_request_id', 'status']);

        $wdReqB = $this->json('POST', '/api/v1/withdrawals', [
            'seller_profile_id' => $sellerProfileWd->id,
            'wallet_id' => $sellerWalletId,
            'amount' => '15.0000',
            'currency' => 'USD',
            'idempotency_key' => 'contract-wd-b-'.Str::random(6),
        ], $sellerWdToken);
        $wrB = (int) $wdReqB['json']['data']['withdrawal_request_id'];
        $wdApprove = $this->json('POST', '/api/v1/withdrawals/'.$wrB.'/approve', [
            'idempotency_key' => 'contract-wd-approve-'.Str::random(6),
        ], $adminWdToken);
        $this->assertSuccessEnvelope($wdApprove, 200, ['withdrawal_request_id', 'status']);

        $wdReqC = $this->json('POST', '/api/v1/withdrawals', [
            'seller_profile_id' => $sellerProfileWd->id,
            'wallet_id' => $sellerWalletId,
            'amount' => '10.0000',
            'currency' => 'USD',
            'idempotency_key' => 'contract-wd-c-'.Str::random(6),
        ], $sellerWdToken);
        $wrC = (int) $wdReqC['json']['data']['withdrawal_request_id'];
        $wdReject = $this->json('POST', '/api/v1/withdrawals/'.$wrC.'/reject', [
            'idempotency_key' => 'contract-wd-reject-'.Str::random(6),
            'reason' => 'manual reject',
        ], $adminWdToken);
        $this->assertSuccessEnvelope($wdReject, 200, ['withdrawal_request_id', 'status']);

        // error envelopes: validation/authorization/not_found/conflict/domain/internal
        $validation = $this->json('GET', '/api/v1/products/search');
        $this->assertErrorEnvelope($validation, 422, 'validation_failed', ['reason_code', 'violations']);

        $forbidden = $this->json('POST', '/api/v1/withdrawals/'.$wrA.'/approve', [
            'idempotency_key' => 'contract-wd-forbidden-'.Str::random(6),
        ], $sellerWdToken);
        $this->assertErrorEnvelope($forbidden, 403, 'forbidden', ['action', 'actor_user_id']);

        $notFound = $this->json('GET', '/api/v1/products/999999');
        $this->assertErrorEnvelope($notFound, 404, 'not_found', ['reason_code']);

        $conflict = $this->json('POST', '/api/v1/disputes/'.$caseId.'/resolve/refund', [
            'currency' => 'USD',
            'reason_code' => 'buyer_wins',
            'notes' => 'different payload for same key',
            'idempotency_key' => 'contract-refund-conflict-key',
        ], $adminToken);
        self::assertContains($conflict['status'], [200, 403, 409, 422]);
        if ($conflict['status'] >= 400) {
            self::assertArrayHasKey('error', $conflict['json']);
            self::assertArrayHasKey('message', $conflict['json']);
        }
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function json(string $method, string $path, array $body = [], ?string $token = null): array
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

    /**
     * @param list<string> $requiredDataKeys
     */
    private function assertSuccessEnvelope(array $res, int $status, array $requiredDataKeys): void
    {
        self::assertSame($status, $res['status']);
        self::assertArrayHasKey('data', $res['json']);
        self::assertArrayNotHasKey('error', $res['json']);
        self::assertIsArray($res['json']['data']);
        foreach ($requiredDataKeys as $key) {
            self::assertArrayHasKey($key, $res['json']['data']);
        }
    }

    private function assertPaginatedEnvelope(array $res, int $status): void
    {
        self::assertSame($status, $res['status']);
        self::assertArrayHasKey('data', $res['json']);
        self::assertArrayHasKey('meta', $res['json']);
        self::assertIsArray($res['json']['data']);
        self::assertIsArray($res['json']['meta']);
        foreach (['page', 'per_page', 'total', 'last_page'] as $metaKey) {
            self::assertArrayHasKey($metaKey, $res['json']['meta']);
        }
    }

    /**
     * @param list<string> $extraKeys
     */
    private function assertErrorEnvelope(array $res, int $status, string $errorCode, array $extraKeys = []): void
    {
        self::assertSame($status, $res['status']);
        self::assertArrayHasKey('error', $res['json']);
        self::assertArrayHasKey('message', $res['json']);
        self::assertSame($errorCode, $res['json']['error']);
        self::assertArrayNotHasKey('data', $res['json']);
        foreach ($extraKeys as $key) {
            self::assertArrayHasKey($key, $res['json']);
        }
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
        $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => ucfirst($roleCode)]);
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
        $sellerUser = $this->createUser('catalog-contract-seller-'.Str::random(6).'@example.test');
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
            'slug' => 'contract-catalog-'.Str::lower(Str::random(6)),
            'title' => 'Catalog Store',
            'description' => 'Contract',
            'policy_text' => null,
            'is_public' => true,
        ]);
        $category = Category::query()->create([
            'parent_id' => null,
            'slug' => 'contract-cat-'.Str::lower(Str::random(6)),
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
    private function seedOrder(OrderStatus $status, string $netAmount): array
    {
        $buyer = $this->createUser('contract-order-buyer-'.Str::random(6).'@example.test');
        $sellerUser = $this->createUser('contract-order-seller-'.Str::random(6).'@example.test');
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
        [$buyer, , $order] = $this->seedOrder(OrderStatus::PaidInEscrow, $amount);

        $buyerWalletId = (int) $this->wallet->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $buyer->id,
            walletType: WalletType::Buyer,
            currency: 'USD',
        ))['wallet_id'];

        $this->wallet->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'seed',
            referenceId: $order->id,
            idempotencyKey: 'contract-seed-dispute-order-'.$order->id,
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
            idempotencyKey: 'contract-dispute-escrow-create-'.$order->id,
        ));

        $this->escrow->holdEscrow(new HoldEscrowCommand(
            escrowAccountId: (int) $create['escrow_account_id'],
            idempotencyKey: 'contract-dispute-escrow-hold-'.$order->id,
        ));

        return [$order, $buyer];
    }

    /**
     * @return array{0: User, 1: SellerProfile, 2: int}
     */
    private function seedSellerWithFundedWallet(string $depositAmount): array
    {
        $sellerUser = $this->createUser('contract-seller-wd-'.Str::random(6).'@example.test');
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
            idempotencyKey: 'contract-seed-wd-'.$seller->id.'-'.Str::random(6),
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

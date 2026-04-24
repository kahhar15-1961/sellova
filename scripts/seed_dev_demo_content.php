<?php

declare(strict_types=1);

use App\Domain\Commands\Escrow\CreateEscrowForOrderCommand;
use App\Domain\Commands\Escrow\HoldEscrowCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Commands\WalletLedger\PostLedgerBatchCommand;
use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WalletLedgerEntrySide;
use App\Domain\Enums\WalletLedgerEntryType;
use App\Domain\Enums\WalletType;
use App\Domain\Value\LedgerPostingLine;
use App\Http\Application;
use App\Http\HttpKernel;
use App\Models\Category;
use App\Models\DisputeCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Storefront;
use App\Models\User;
use App\Models\UserAuthToken;
use App\Services\Escrow\EscrowService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;

/**
 * Demo catalog, orders, escrow + dispute, withdrawal request for dev accounts
 * created in {@see seed_dev_data.php}.
 */
function run_dev_demo_content(): void
{
    $buyer = User::query()->where('email', 'dev-buyer@sellova.local')->first();
    $sellerUser = User::query()->where('email', 'dev-seller@sellova.local')->first();
    if ($buyer === null || $sellerUser === null) {
        fwrite(STDERR, "Missing dev-buyer or dev-seller user. Run seed_dev_data.php first.\n");

        return;
    }

    $sellerProfile = SellerProfile::query()->where('user_id', $sellerUser->id)->first();
    if ($sellerProfile === null) {
        fwrite(STDERR, "dev-seller has no seller_profiles row.\n");

        return;
    }

    $walletSvc = new WalletLedgerService();
    $escrowSvc = new EscrowService($walletSvc);

    $storefront = Storefront::query()->updateOrCreate(
        ['seller_profile_id' => $sellerProfile->id],
        [
            'uuid' => (string) Str::uuid(),
            'slug' => 'dev-seed-store',
            'title' => 'Dev Seed Storefront',
            'description' => 'Auto-seeded for local QA',
            'policy_text' => null,
            'is_public' => true,
        ],
    );

    $category = Category::query()->updateOrCreate(
        ['slug' => 'dev-seed-category'],
        [
            'parent_id' => null,
            'name' => 'Dev Seed Category',
            'is_active' => true,
            'sort_order' => 1,
        ],
    );

    $pubAt = now()->subMinutes(5);
    Product::query()->updateOrCreate(
        [
            'seller_profile_id' => $sellerProfile->id,
            'title' => 'Dev Seed — Demo Handset',
        ],
        [
            'uuid' => (string) Str::uuid(),
            'storefront_id' => $storefront->id,
            'category_id' => $category->id,
            'product_type' => 'physical',
            'description' => 'Seeded product for catalog / product detail screens.',
            'base_price' => '199.9900',
            'currency' => 'USD',
            'status' => 'published',
            'published_at' => $pubAt,
        ],
    );
    Product::query()->updateOrCreate(
        [
            'seller_profile_id' => $sellerProfile->id,
            'title' => 'Dev Seed — USB Cable',
        ],
        [
            'uuid' => (string) Str::uuid(),
            'storefront_id' => $storefront->id,
            'category_id' => $category->id,
            'product_type' => 'physical',
            'description' => 'Second seeded listing for pagination.',
            'base_price' => '12.5000',
            'currency' => 'USD',
            'status' => 'published',
            'published_at' => now(),
        ],
    );

    $netDraft = '42.0000';
    $draftOrder = Order::query()->firstOrCreate(
        ['order_number' => 'DEV-SEED-DRAFT-001'],
        [
            'uuid' => (string) Str::uuid(),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::Draft,
            'currency' => 'USD',
            'gross_amount' => $netDraft,
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => $netDraft,
            'placed_at' => null,
        ],
    );
    OrderItem::query()->firstOrCreate(
        [
            'order_id' => $draftOrder->id,
        ],
        [
            'uuid' => (string) Str::uuid(),
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Dev Seed Line',
            'sku_snapshot' => 'DEV-SKU-DRAFT',
            'quantity' => 1,
            'unit_price_snapshot' => $netDraft,
            'line_total_snapshot' => $netDraft,
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ],
    );

    $escrowOrder = Order::query()->firstOrCreate(
        ['order_number' => 'DEV-SEED-ESCROW-001'],
        [
            'uuid' => (string) Str::uuid(),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::PaidInEscrow,
            'currency' => 'USD',
            'gross_amount' => '80.0000',
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => '80.0000',
            'placed_at' => now(),
        ],
    );
    OrderItem::query()->firstOrCreate(
        [
            'order_id' => $escrowOrder->id,
        ],
        [
            'uuid' => (string) Str::uuid(),
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Dev Seed Escrow Item',
            'sku_snapshot' => 'DEV-SKU-ESCROW',
            'quantity' => 1,
            'unit_price_snapshot' => '80.0000',
            'line_total_snapshot' => '80.0000',
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ],
    );

    $buyerWalletId = (int) $walletSvc->createWalletIfMissing(new CreateWalletIfMissingCommand(
        userId: $buyer->id,
        walletType: WalletType::Buyer,
        currency: 'USD',
    ))['wallet_id'];
    $walletSvc->createWalletIfMissing(new CreateWalletIfMissingCommand(
        userId: $sellerProfile->user_id,
        walletType: WalletType::Seller,
        currency: 'USD',
    ));

    $escrowAccount = \App\Models\EscrowAccount::query()->where('order_id', $escrowOrder->id)->first();
    if ($escrowAccount === null) {
        $walletSvc->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'dev_seed',
            referenceId: $escrowOrder->id,
            idempotencyKey: 'dev-seed-buyer-float-'.$escrowOrder->id,
            entries: [
                new LedgerPostingLine(
                    walletId: $buyerWalletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: '200.0000',
                    currency: 'USD',
                    referenceType: 'dev_seed',
                    referenceId: $escrowOrder->id,
                    counterpartyWalletId: null,
                    description: 'dev_seed_buyer_balance',
                ),
            ],
        ));

        $create = $escrowSvc->createEscrowForOrder(new CreateEscrowForOrderCommand(
            orderId: $escrowOrder->id,
            currency: 'USD',
            heldAmount: '80.0000',
            idempotencyKey: 'dev-seed-escrow-create-'.$escrowOrder->id,
        ));
        $escrowSvc->holdEscrow(new HoldEscrowCommand(
            escrowAccountId: (int) $create['escrow_account_id'],
            idempotencyKey: 'dev-seed-escrow-hold-'.$escrowOrder->id,
        ));
    }

    $sellerWalletId = (int) $walletSvc->createWalletIfMissing(new CreateWalletIfMissingCommand(
        userId: $sellerProfile->user_id,
        walletType: WalletType::Seller,
        currency: 'USD',
    ))['wallet_id'];

    try {
        $walletSvc->postLedgerBatch(new PostLedgerBatchCommand(
            eventName: LedgerPostingEventName::Deposit,
            referenceType: 'dev_seed',
            referenceId: $sellerProfile->id,
            idempotencyKey: 'dev-seed-seller-float-'.$sellerProfile->id,
            entries: [
                new LedgerPostingLine(
                    walletId: $sellerWalletId,
                    entrySide: WalletLedgerEntrySide::Credit,
                    entryType: WalletLedgerEntryType::DepositCredit,
                    amount: '500.0000',
                    currency: 'USD',
                    referenceType: 'dev_seed',
                    referenceId: $sellerProfile->id,
                    counterpartyWalletId: null,
                    description: 'dev_seed_seller_withdrawable',
                ),
            ],
        ));
    } catch (\Throwable) {
        // Replay-safe: idempotency may already have consumed this key.
    }

    $app = new Application();
    $routes = (require __DIR__.'/../routes/api.php')($app);
    $kernel = new HttpKernel($app, $routes);

    $json = static function (string $method, string $path, array $body, ?string $bearer) use ($kernel): array {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        if ($bearer !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }
        $content = in_array(strtoupper($method), ['GET', 'HEAD'], true) ? '' : (string) json_encode($body);
        $request = Request::create($path, strtoupper($method), [], [], [], $server, $content);
        $response = $kernel->handle($request);
        $decoded = json_decode($response->getContent(), true);

        return [
            'status' => $response->getStatusCode(),
            'json' => is_array($decoded) ? $decoded : [],
        ];
    };

    $issueToken = static function (User $user): string {
        $plain = 'at_'.Str::random(48);
        UserAuthToken::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'token_family' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $plain),
            'kind' => UserAuthToken::KIND_ACCESS,
            'expires_at' => now()->addHours(6),
            'revoked_at' => null,
            'created_at' => now(),
        ]);

        return $plain;
    };

    $buyerToken = $issueToken($buyer);
    $sellerToken = $issueToken($sellerUser);

    $pendingOrder = Order::query()->firstOrCreate(
        ['order_number' => 'DEV-SEED-PENDING-001'],
        [
            'uuid' => (string) Str::uuid(),
            'buyer_user_id' => $buyer->id,
            'status' => OrderStatus::Draft,
            'currency' => 'USD',
            'gross_amount' => '15.0000',
            'discount_amount' => '0.0000',
            'fee_amount' => '0.0000',
            'net_amount' => '15.0000',
            'placed_at' => null,
        ],
    );
    OrderItem::query()->firstOrCreate(
        [
            'order_id' => $pendingOrder->id,
        ],
        [
            'uuid' => (string) Str::uuid(),
            'product_type_snapshot' => 'physical',
            'title_snapshot' => 'Pending payment item',
            'sku_snapshot' => 'DEV-SKU-PEND',
            'quantity' => 1,
            'unit_price_snapshot' => '15.0000',
            'line_total_snapshot' => '15.0000',
            'commission_rule_snapshot_json' => [],
            'delivery_state' => 'not_started',
        ],
    );
    $pendingOrder->refresh();
    if ($pendingOrder->status === OrderStatus::Draft) {
        $r = $json('POST', '/api/v1/orders/'.$pendingOrder->id.'/mark-pending-payment', [], $buyerToken);
        if ($r['status'] !== 200) {
            fwrite(STDERR, "mark-pending-payment DEV-SEED-PENDING-001: HTTP {$r['status']}\n");
        }
    }

    $escrowOrder->refresh();
    $hasActiveDispute = DisputeCase::query()
        ->where('order_id', $escrowOrder->id)
        ->where('status', '!=', DisputeCaseStatus::Resolved->value)
        ->exists();

    if ($escrowOrder->status === OrderStatus::PaidInEscrow && ! $hasActiveDispute) {
        $openDispute = $json('POST', '/api/v1/orders/'.$escrowOrder->id.'/disputes', [
            'reason_code' => 'item_not_received',
            'idempotency_key' => 'dev-seed-dispute-open-001',
        ], $buyerToken);
        if (! in_array($openDispute['status'], [200, 201], true)) {
            fwrite(STDERR, "Open dispute: HTTP {$openDispute['status']} ".json_encode($openDispute['json'])."\n");
        }
    }

    $wr = $json('POST', '/api/v1/withdrawals', [
        'seller_profile_id' => $sellerProfile->id,
        'wallet_id' => $sellerWalletId,
        'amount' => '75.0000',
        'currency' => 'USD',
        'idempotency_key' => 'dev-seed-withdrawal-001',
    ], $sellerToken);
    if (! in_array($wr['status'], [200, 201], true) && $wr['status'] !== 409) {
        fwrite(STDERR, "Withdrawal request: HTTP {$wr['status']} ".json_encode($wr['json'])."\n");
    }

    echo "\nDemo content: products, storefront, draft + pending_payment + paid_in_escrow orders, escrow, dispute, seller withdrawal request.\n";
}

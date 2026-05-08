<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\WithdrawalRequest;
use App\Services\TimeoutAutomation\TimeoutAutomationService;
use App\Services\WalletLedger\WalletLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderShowController extends AdminPageController
{
    public function __invoke(Request $request, Order $order): Response
    {
        $order->load([
            'buyer:id,email,status',
            'buyer.wallets:id,user_id,wallet_type,currency,status,version',
            'paymentIntents.paymentTransactions',
            'paymentTransactions.payment_intent',
            'orderItems.seller_profile.user:id,email,status',
            'orderItems.product:id,title,base_price,currency,status,seller_profile_id',
            'escrowAccount',
            'orderStateTransitions' => static fn ($q) => $q->orderByDesc('id')->limit(50),
        ]);

        $sellerProfile = $order->orderItems->first()?->seller_profile;
        if ($sellerProfile !== null) {
            $sellerProfile->loadMissing(['user:id,email,status', 'storefront:id,seller_profile_id,title,is_public']);
        }

        $walletLedger = app(WalletLedgerService::class);
        $latestIntent = $order->paymentIntents->sortByDesc('id')->first();
        $latestTxn = $order->paymentTransactions->sortByDesc('id')->first();
        $paymentMethod = $this->resolvePaymentMethod($latestIntent, $latestTxn);
        $paymentProvider = $latestIntent?->provider ?? data_get($latestTxn?->raw_payload_json, 'provider');
        $buyerWallets = $order->buyer?->wallets ?? collect();
        $buyerWalletSummaries = $buyerWallets->map(function ($wallet) use ($walletLedger): array {
            $balances = $walletLedger->computeWalletBalances(new ComputeWalletBalancesCommand((int) $wallet->id));

            return [
                'id' => $wallet->id,
                'type' => $wallet->wallet_type->value,
                'currency' => $wallet->currency,
                'status' => $wallet->status->value,
                'available_balance' => (string) ($balances['available_balance'] ?? '0.0000'),
                'held_balance' => (string) ($balances['held_balance'] ?? '0.0000'),
                'href' => route('admin.wallets.show', $wallet),
            ];
        })->values()->all();

        $sellerWalletSummaries = [];
        $recentWithdrawals = [];
        $sellerReviews = [];
        $sellerProducts = [];
        if ($sellerProfile !== null) {
            $sellerProfile->user?->loadMissing(['wallets:id,user_id,wallet_type,status,currency,version']);
            $sellerWallets = $sellerProfile->user?->wallets ?? collect();
            $sellerWalletSummaries = $sellerWallets->map(function ($wallet) use ($walletLedger): array {
                $balances = $walletLedger->computeWalletBalances(new ComputeWalletBalancesCommand((int) $wallet->id));

                return [
                    'id' => $wallet->id,
                    'type' => $wallet->wallet_type->value,
                    'currency' => $wallet->currency,
                    'status' => $wallet->status->value,
                    'available_balance' => (string) ($balances['available_balance'] ?? '0.0000'),
                    'held_balance' => (string) ($balances['held_balance'] ?? '0.0000'),
                    'href' => route('admin.wallets.show', $wallet),
                ];
            })->values()->all();

            $recentWithdrawals = WithdrawalRequest::query()
                ->where('seller_profile_id', $sellerProfile->id)
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(static fn (WithdrawalRequest $w): array => [
                    'id' => $w->id,
                    'status' => $w->status->value,
                    'requested_amount' => (string) $w->requested_amount,
                    'net_payout_amount' => (string) $w->net_payout_amount,
                    'currency' => $w->currency,
                    'created_at' => $w->created_at?->toIso8601String(),
                    'href' => route('admin.withdrawals.show', $w),
                ])->values()->all();

            $sellerProducts = Product::query()
                ->where('seller_profile_id', $sellerProfile->id)
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'title', 'status', 'currency', 'base_price', 'updated_at'])
                ->map(function (Product $p): array {
                    return [
                    'id' => $p->id,
                    'title' => $p->title ?? '#'.$p->id,
                    'status' => (string) $p->status,
                    'price' => $this->money((string) ($p->currency ?? ''), (string) $p->base_price),
                    'updated_at' => $p->updated_at?->toIso8601String(),
                    'href' => route('admin.products.show', $p),
                    ];
                })->values()->all();

            $sellerReviews = Review::query()
                ->where('seller_profile_id', $sellerProfile->id)
                ->with(['product:id,title'])
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'product_id', 'rating', 'comment', 'created_at'])
                ->map(static fn (Review $r): array => [
                    'id' => $r->id,
                    'product' => $r->product?->title ?? '#'.$r->product_id,
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values()->all();
        }

        $items = $order->orderItems->map(function ($it) use ($order): array {
            return [
                'id' => $it->id,
                'title' => $it->title_snapshot ?? '—',
                'seller' => $it->seller_profile?->display_name ?? '—',
                'product' => $it->product ? [
                    'id' => $it->product->id,
                    'title' => $it->product->title,
                    'status' => (string) $it->product->status,
                    'price' => $this->money((string) ($it->product->currency ?? $order->currency ?? ''), (string) $it->product->base_price),
                    'href' => route('admin.products.show', $it->product),
                ] : null,
                'line_total' => $this->money((string) ($order->currency ?? ''), (string) $it->line_total_snapshot),
                'delivery' => (string) $it->delivery_state,
            ];
        });

        $transitions = $order->orderStateTransitions->map(static fn ($t): array => [
            'from' => (string) ($t->from_state ?? ''),
            'to' => (string) ($t->to_state ?? ''),
            'at' => $t->created_at?->toIso8601String(),
        ]);

        $escrow = $order->escrowAccount;
        $timeoutState = (new TimeoutAutomationService())->timerState($order);

        return Inertia::render('Admin/Orders/Show', [
            'header' => $this->pageHeader(
                'Order '.$order->order_number,
                'Order, buyer, seller, wallet, product, and escrow context in one view.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders', 'href' => route('admin.orders.index')],
                    ['label' => $order->order_number ?? '#'.$order->id],
                ],
            ),
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'currency' => $order->currency,
                'gross_amount' => (string) $order->gross_amount,
                'discount_amount' => (string) $order->discount_amount,
                'fee_amount' => (string) $order->fee_amount,
                'net_amount' => (string) $order->net_amount,
                'payment_method' => $paymentMethod,
                'payment_provider' => $paymentProvider,
                'promo_code' => $order->promo_code,
                'shipping_method' => $order->shipping_method,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'timeout_state' => $timeoutState,
                'buyer_email' => $order->buyer?->email,
                'payment_intent' => $latestIntent ? [
                    'id' => $latestIntent->id,
                    'status' => $latestIntent->status,
                    'provider' => $latestIntent->provider,
                    'amount' => (string) $latestIntent->amount,
                    'currency' => $latestIntent->currency,
                ] : null,
                'payment_transaction' => $latestTxn ? [
                    'id' => $latestTxn->id,
                    'status' => $latestTxn->status,
                    'type' => $latestTxn->txn_type,
                    'amount' => (string) $latestTxn->amount,
                    'currency' => $order->currency,
                    'reference' => $latestTxn->provider_txn_ref,
                ] : null,
            ],
            'buyer' => $order->buyer ? [
                'id' => $order->buyer->id,
                'email' => $order->buyer->email,
                'status' => $order->buyer->status,
                'wallets' => $buyerWalletSummaries,
                'href' => route('admin.buyers.show', $order->buyer),
            ] : null,
            'seller' => $sellerProfile === null ? null : [
                'id' => $sellerProfile->id,
                'display_name' => $sellerProfile->display_name,
                'legal_name' => $sellerProfile->legal_name,
                'verification_status' => $sellerProfile->verification_status,
                'store_status' => $sellerProfile->store_status,
                'account_email' => $sellerProfile->user?->email,
                'href' => route('admin.seller-profiles.show', $sellerProfile),
                'wallets' => $sellerWalletSummaries,
            ],
            'items' => $items,
            'escrow' => $escrow === null ? null : [
                'state' => $escrow->state->value,
                'held_amount' => (string) $escrow->held_amount,
                'released_amount' => (string) $escrow->released_amount,
                'refunded_amount' => (string) $escrow->refunded_amount,
                'currency' => $escrow->currency,
            ],
            'seller_products' => $sellerProducts,
            'seller_reviews' => $sellerReviews,
            'seller_withdrawals' => $recentWithdrawals,
            'transitions' => $transitions,
            'list_href' => route('admin.orders.index'),
        ]);
    }

    private function money(string $currency, string $amount): string
    {
        $formatted = number_format((float) $amount, 2, '.', '');
        $currency = strtoupper(trim($currency));

        return $currency === 'USD' ? '$'.$formatted : ($currency !== '' ? $currency.' '.$formatted : $formatted);
    }

    private function resolvePaymentMethod(?PaymentIntent $intent, ?PaymentTransaction $txn): ?string
    {
        $rawPayload = is_array($txn?->raw_payload_json) ? $txn?->raw_payload_json : [];
        $method = data_get($rawPayload, 'method')
            ?? data_get($rawPayload, 'payment_method')
            ?? data_get($rawPayload, 'channel')
            ?? data_get($rawPayload, 'payment_channel');

        if ($method !== null && $method !== '') {
            return (string) $method;
        }

        return $intent?->provider;
    }
}

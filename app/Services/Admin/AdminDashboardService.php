<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\WithdrawalRequestStatus;
use App\Models\DisputeCase;
use App\Models\KycVerification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregates and queue slices for the admin dashboard.
 */
final class AdminDashboardService
{
    /**
     * @return array{
     *   summary: array<string, array{value: string|null, hint: string|null}>,
     *   recent_orders: list<array<string, mixed>>,
     *   open_disputes: list<array<string, mixed>>,
     *   pending_withdrawals: list<array<string, mixed>>,
     *   pending_wallet_top_ups: list<array<string, mixed>>,
     *   seller_verification_queue: list<array<string, mixed>>,
     *   product_moderation: list<array<string, mixed>>,
     *   system_alerts: list<array<string, mixed>>,
     *   section_access: array<string, bool>,
     *   links: array<string, string>
     * }
     */
    public function buildPage(User $user, string $range = '7d'): array
    {
        $days = match ($range) {
            '24h' => 1,
            '30d' => 30,
            default => 7,
        };
        $summaryRow = $this->fetchSummaryScalars();
        $summary = $this->mapSummaryForPermissions($user, $summaryRow);

        $sectionAccess = [
            'recent_orders' => $user->hasPermissionCode(AdminPermission::ORDERS_VIEW),
            'open_disputes' => $user->hasPermissionCode(AdminPermission::DISPUTES_VIEW),
            'pending_withdrawals' => $user->hasPermissionCode(AdminPermission::WITHDRAWALS_VIEW),
            'pending_wallet_top_ups' => $user->hasPermissionCode(AdminPermission::WALLETS_VIEW),
            'seller_verification_queue' => $user->hasAnyPermissionCode([
                AdminPermission::SELLERS_VIEW,
                AdminPermission::SELLERS_VERIFY,
            ]),
            'product_moderation' => $user->hasAnyPermissionCode([
                AdminPermission::PRODUCTS_VIEW,
                AdminPermission::PRODUCTS_MODERATE,
            ]),
            'system_alerts' => true,
        ];

        return [
            'summary' => $summary,
            'recent_orders' => $sectionAccess['recent_orders'] ? $this->recentOrders() : [],
            'open_disputes' => $sectionAccess['open_disputes'] ? $this->openDisputes() : [],
            'pending_withdrawals' => $sectionAccess['pending_withdrawals'] ? $this->pendingWithdrawals() : [],
            'pending_wallet_top_ups' => $sectionAccess['pending_wallet_top_ups'] ? $this->pendingWalletTopUps() : [],
            'seller_verification_queue' => $sectionAccess['seller_verification_queue']
                ? $this->sellerVerificationQueue()
                : [],
            'product_moderation' => $sectionAccess['product_moderation'] ? $this->productModerationQueue() : [],
            'system_alerts' => $this->systemAlertsFromRow($summaryRow),
            'trend_range' => $range,
            'trends' => $this->buildTrends($days),
            'section_access' => $sectionAccess,
            'links' => [
                'orders' => route('admin.orders.index'),
                'disputes' => route('admin.disputes.index'),
                'withdrawals' => route('admin.withdrawals.index'),
                'wallet_top_ups' => route('admin.wallet-top-ups.index'),
                'sellers' => route('admin.sellers.index'),
                'products' => route('admin.products.index'),
                'escrows' => route('admin.escrows.index'),
                'audit_logs' => route('admin.audit-logs.index'),
            ],
        ];
    }

    /**
     * @return array{orders: list<array{label: string, value: int}>, disputes: list<array{label: string, value: int}>, withdrawals: list<array{label: string, value: int}>}
     */
    private function buildTrends(int $days): array
    {
        $windowStart = now()->subDays(max(0, $days - 1))->startOfDay();
        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $d = (clone $windowStart)->addDays($i);
            $labels[$d->format('Y-m-d')] = $d->format('M j');
        }

        $buildSeries = static function (array $rows, array $labels): array {
            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row->day] = (int) $row->count;
            }

            $series = [];
            foreach ($labels as $day => $label) {
                $series[] = ['label' => $label, 'value' => $map[$day] ?? 0];
            }

            return $series;
        };

        $orders = DB::table('orders')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>=', $windowStart)
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->all();

        $disputes = DB::table('dispute_cases')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>=', $windowStart)
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->all();

        $withdrawals = DB::table('withdrawal_requests')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>=', $windowStart)
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->all();

        return [
            'orders' => $buildSeries($orders, $labels),
            'disputes' => $buildSeries($disputes, $labels),
            'withdrawals' => $buildSeries($withdrawals, $labels),
        ];
    }

    /**
     * Single round-trip scalar snapshot (MySQL-friendly).
     *
     * @return object{
     *   total_users: int,
     *   total_sellers: int,
     *   pending_seller_verifications: int,
     *   total_products: int,
     *   total_orders: int,
     *   orders_in_escrow: int,
     *   open_disputes: int,
     *   pending_withdrawals: int,
     *   pending_wallet_top_ups: int,
     *   gmv: string|null,
     *   gmv_currency_count: int,
     *   released_funds: string|null,
     *   refunded_funds: string|null,
     *   failed_webhooks: int,
     *   stale_webhooks: int,
     *   failed_outbox: int,
     *   stuck_outbox: int,
     *   escalated_seller_verifications: int,
     *   escalated_disputes: int,
     *   escalated_withdrawals: int
     * }
     */
    private function fetchSummaryScalars(): object
    {
        $gmvStatuses = implode("','", [
            OrderStatus::Paid->value,
            OrderStatus::PaidInEscrow->value,
            OrderStatus::Processing->value,
            OrderStatus::ShippedOrDelivered->value,
            OrderStatus::Completed->value,
            OrderStatus::Disputed->value,
        ]);

        $row = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS total_users,
                (SELECT COUNT(*) FROM seller_profiles WHERE deleted_at IS NULL) AS total_sellers,
                (
                    SELECT COUNT(*)
                    FROM seller_profiles sp
                    WHERE sp.deleted_at IS NULL
                      AND (
                        sp.verification_status = 'pending'
                        OR EXISTS (
                            SELECT 1 FROM kyc_verifications k
                            WHERE k.seller_profile_id = sp.id
                              AND k.status IN ('submitted','under_review')
                        )
                      )
                ) AS pending_seller_verifications,
                (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL) AS total_products,
                (SELECT COUNT(*) FROM orders) AS total_orders,
                (
                    SELECT COUNT(DISTINCT o.id)
                    FROM orders o
                    WHERE o.status = 'paid_in_escrow'
                       OR EXISTS (
                            SELECT 1 FROM escrow_accounts e
                            WHERE e.order_id = o.id
                              AND e.state IN ('held','under_dispute')
                       )
                ) AS orders_in_escrow,
                (SELECT COUNT(*) FROM dispute_cases WHERE status <> '".DisputeCaseStatus::Resolved->value."') AS open_disputes,
                (
                    SELECT COUNT(*) FROM withdrawal_requests
                    WHERE status IN ('".WithdrawalRequestStatus::Requested->value."','".WithdrawalRequestStatus::UnderReview->value."')
                ) AS pending_withdrawals,
                (
                    SELECT COUNT(*) FROM wallet_top_up_requests
                    WHERE status = 'requested'
                ) AS pending_wallet_top_ups,
                (
                    SELECT COALESCE(SUM(gross_amount), 0)
                    FROM orders
                    WHERE status IN ('{$gmvStatuses}')
                ) AS gmv,
                (
                    SELECT COUNT(DISTINCT currency)
                    FROM orders
                    WHERE status IN ('{$gmvStatuses}')
                ) AS gmv_currency_count,
                (SELECT COALESCE(SUM(released_amount), 0) FROM escrow_accounts) AS released_funds,
                (SELECT COALESCE(SUM(refunded_amount), 0) FROM escrow_accounts) AS refunded_funds,
                (SELECT COUNT(*) FROM payment_webhook_events WHERE processing_status = 'failed') AS failed_webhooks,
                (
                    SELECT COUNT(*) FROM payment_webhook_events
                    WHERE processing_status = 'pending' AND received_at < DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 HOUR)
                ) AS stale_webhooks,
                (SELECT COUNT(*) FROM outbox_events WHERE status = 'failed') AS failed_outbox,
                (
                    SELECT COUNT(*) FROM outbox_events
                    WHERE status = 'pending' AND attempts > 0 AND available_at < UTC_TIMESTAMP(6)
                ) AS stuck_outbox,
                (
                    SELECT COUNT(*) FROM dispute_cases
                    WHERE status <> '".DisputeCaseStatus::Resolved->value."'
                      AND escalated_at IS NOT NULL
                ) AS escalated_disputes,
                (
                    SELECT COUNT(*) FROM withdrawal_requests
                    WHERE status IN ('".WithdrawalRequestStatus::Requested->value."','".WithdrawalRequestStatus::UnderReview->value."')
                      AND escalated_at IS NOT NULL
                ) AS escalated_withdrawals
                ,
                (
                    SELECT COUNT(*) FROM kyc_verifications
                    WHERE status IN ('submitted','under_review')
                      AND escalated_at IS NOT NULL
                ) AS escalated_seller_verifications
        ");

        return $row ?? (object) [];
    }

    /**
     * @return array<string, array{value: string|null, hint: string|null}>
     */
    private function mapSummaryForPermissions(User $user, object $row): array
    {
        $n = static fn (mixed $v): int => max(0, (int) ($v ?? 0));

        $def = static fn (bool $can, string|int|float|null $display, ?string $hint = null): array => [
            'value' => $can ? (string) $display : null,
            'hint' => $can ? $hint : 'Requires additional permission',
        ];

        $fmtMoney = static function (mixed $amount, int $currencyCount): array {
            $a = (string) ($amount ?? '0');
            if ($currencyCount > 1) {
                return ['display' => number_format((float) $a, 2, '.', ','), 'hint' => 'Sum spans multiple order currencies; add FX reporting for production.'];
            }

            return ['display' => number_format((float) $a, 2, '.', ','), 'hint' => null];
        };

        $gmvPack = $fmtMoney($row->gmv ?? 0, $n($row->gmv_currency_count ?? 1));

        return [
            'total_users' => $def(
                $user->hasPermissionCode(AdminPermission::USERS_VIEW),
                number_format($n($row->total_users)),
            ),
            'total_sellers' => $def(
                $user->hasPermissionCode(AdminPermission::SELLERS_VIEW),
                number_format($n($row->total_sellers)),
            ),
            'pending_seller_verifications' => $def(
                $user->hasAnyPermissionCode([AdminPermission::SELLERS_VIEW, AdminPermission::SELLERS_VERIFY]),
                number_format($n($row->pending_seller_verifications)),
            ),
            'total_products' => $def(
                $user->hasPermissionCode(AdminPermission::PRODUCTS_VIEW),
                number_format($n($row->total_products)),
            ),
            'total_orders' => $def(
                $user->hasPermissionCode(AdminPermission::ORDERS_VIEW),
                number_format($n($row->total_orders)),
            ),
            'orders_in_escrow' => $def(
                $user->hasAnyPermissionCode([AdminPermission::ORDERS_VIEW, AdminPermission::ESCROWS_VIEW]),
                number_format($n($row->orders_in_escrow)),
                'Paid in escrow or active held / disputed escrow.',
            ),
            'open_disputes' => $def(
                $user->hasPermissionCode(AdminPermission::DISPUTES_VIEW),
                number_format($n($row->open_disputes)),
            ),
            'pending_withdrawals' => $def(
                $user->hasPermissionCode(AdminPermission::WITHDRAWALS_VIEW),
                number_format($n($row->pending_withdrawals)),
                'Requested or under review.',
            ),
            'pending_wallet_top_ups' => $def(
                $user->hasPermissionCode(AdminPermission::WALLETS_VIEW),
                number_format($n($row->pending_wallet_top_ups)),
                'Manual wallet funding requests awaiting finance review.',
            ),
            'escalated_cases' => $def(
                $user->hasAnyPermissionCode([AdminPermission::DISPUTES_VIEW, AdminPermission::WITHDRAWALS_VIEW]),
                number_format($n($row->escalated_disputes) + $n($row->escalated_withdrawals) + $n($row->escalated_seller_verifications)),
                'Open items escalated by SLA engine.',
            ),
            'total_gmv' => $def(
                $user->hasAnyPermissionCode([AdminPermission::ORDERS_VIEW, AdminPermission::ESCROWS_VIEW]),
                $gmvPack['display'],
                $gmvPack['hint'],
            ),
            'released_funds' => $def(
                $user->hasPermissionCode(AdminPermission::ESCROWS_VIEW),
                number_format((float) ($row->released_funds ?? 0), 2, '.', ','),
                'Σ escrow released_amount (all currencies).',
            ),
            'refunded_funds' => $def(
                $user->hasPermissionCode(AdminPermission::ESCROWS_VIEW),
                number_format((float) ($row->refunded_funds ?? 0), 2, '.', ','),
                'Σ escrow refunded_amount (all currencies).',
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Order::query()
            ->select(['id', 'order_number', 'status', 'gross_amount', 'currency', 'placed_at', 'buyer_user_id'])
            ->whereNotNull('placed_at')
            ->orderByDesc('placed_at')
            ->limit(8)
            ->with(['buyer' => static function ($q): void {
                $q->select(['id', 'email']);
            }])
            ->get()
            ->map(static function (Order $o): array {
                return [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'status' => $o->status->value,
                    'gross_amount' => (string) $o->gross_amount,
                    'currency' => $o->currency,
                    'placed_at' => $o->placed_at?->toIso8601String(),
                    'buyer_email' => $o->buyer?->email,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openDisputes(): array
    {
        return DisputeCase::query()
            ->select(['id', 'uuid', 'order_id', 'status', 'opened_at'])
            ->where('status', '!=', DisputeCaseStatus::Resolved)
            ->orderByDesc('opened_at')
            ->limit(8)
            ->with(['order' => static function ($q): void {
                $q->select(['id', 'order_number']);
            }])
            ->get()
            ->map(static function (DisputeCase $d): array {
                return [
                    'id' => $d->id,
                    'uuid' => $d->uuid,
                    'status' => $d->status->value,
                    'opened_at' => $d->opened_at?->toIso8601String(),
                    'order_number' => $d->order?->order_number,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingWithdrawals(): array
    {
        return WithdrawalRequest::query()
            ->select(['id', 'uuid', 'status', 'requested_amount', 'currency', 'created_at', 'seller_profile_id'])
            ->whereIn('status', [
                WithdrawalRequestStatus::Requested,
                WithdrawalRequestStatus::UnderReview,
            ])
            ->orderByDesc('created_at')
            ->limit(8)
            ->with(['seller_profile' => static function ($q): void {
                $q->select(['id', 'display_name']);
            }])
            ->get()
            ->map(static function (WithdrawalRequest $w): array {
                return [
                    'id' => $w->id,
                    'uuid' => $w->uuid,
                    'status' => $w->status->value,
                    'requested_amount' => (string) $w->requested_amount,
                    'currency' => $w->currency,
                    'created_at' => $w->created_at?->toIso8601String(),
                    'seller_display_name' => $w->seller_profile?->display_name,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingWalletTopUps(): array
    {
        return WalletTopUpRequest::query()
            ->select(['id', 'uuid', 'wallet_id', 'status', 'requested_amount', 'currency', 'payment_method', 'payment_reference', 'created_at'])
            ->where('status', 'requested')
            ->orderByDesc('created_at')
            ->limit(8)
            ->with(['wallet.user' => static function ($q): void {
                $q->select(['id', 'email']);
            }])
            ->get()
            ->map(static function (WalletTopUpRequest $request): array {
                return [
                    'id' => $request->id,
                    'request' => '#'.$request->id,
                    'user' => $request->wallet?->user?->email ?? '—',
                    'method' => match ($request->payment_method) {
                        'bkash' => 'bKash',
                        'nagad' => 'Nagad',
                        'bank' => 'Bank transfer',
                        'card' => 'Card',
                        'manual' => 'Manual review',
                        default => '—',
                    },
                    'reference' => $request->payment_reference ?: '—',
                    'amount' => trim(($request->currency ?? '').' '.(string) $request->requested_amount),
                    'created_at' => $request->created_at?->toIso8601String(),
                    'href' => route('admin.wallet-top-ups.show', $request),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sellerVerificationQueue(): array
    {
        return KycVerification::query()
            ->select(['id', 'uuid', 'seller_profile_id', 'status', 'assigned_to_user_id', 'assigned_at', 'sla_due_at', 'sla_warning_sent_at', 'escalated_at', 'submitted_at', 'created_at'])
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderByDesc('submitted_at')
            ->limit(8)
            ->with([
                'seller_profile' => static function ($q): void {
                    $q->select(['id', 'display_name', 'verification_status', 'user_id']);
                },
                'assigned_to_user' => static function ($q): void {
                    $q->select(['id', 'email']);
                },
            ])
            ->get()
            ->map(static function (KycVerification $k): array {
                return [
                    'id' => $k->id,
                    'uuid' => $k->uuid,
                    'status' => $k->status,
                    'submitted_at' => $k->submitted_at?->toIso8601String(),
                    'seller_display_name' => $k->seller_profile?->display_name,
                    'seller_verification_status' => $k->seller_profile?->verification_status,
                    'assigned_to_email' => $k->assigned_to_user?->email,
                    'sla_state' => $k->escalated_at !== null ? 'breach' : (($k->submitted_at ?? $k->created_at)?->diffInHours(now()) >= (int) config('admin_sla.kyc.warning_hours', 12) ? 'warning' : 'ok'),
                    'workspace_url' => route('admin.sellers.kyc.show', ['kyc' => $k->id]),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productModerationQueue(): array
    {
        return Product::query()
            ->select(['id', 'uuid', 'title', 'status', 'updated_at', 'seller_profile_id'])
            ->whereNull('deleted_at')
            ->where(static function (Builder $q): void {
                $q->where('status', 'inactive')
                    ->orWhere(static function (Builder $inner): void {
                        $inner->where('status', 'draft')->whereNotNull('title');
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->with(['seller_profile' => static function ($q): void {
                $q->select(['id', 'display_name']);
            }])
            ->get()
            ->map(static function (Product $p): array {
                return [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'title' => $p->title,
                    'status' => $p->status,
                    'updated_at' => $p->updated_at?->toIso8601String(),
                    'seller_display_name' => $p->seller_profile?->display_name,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{severity: string, title: string, detail: string, href?: string}>
     */
    private function systemAlertsFromRow(object $row): array
    {
        $alerts = [];

        if ($n = max(0, (int) ($row->failed_webhooks ?? 0))) {
            $alerts[] = [
                'severity' => 'danger',
                'title' => 'Payment webhooks failed',
                'detail' => "{$n} webhook event(s) marked failed — investigate provider delivery.",
            ];
        }

        if ($n = max(0, (int) ($row->stale_webhooks ?? 0))) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Stale webhook backlog',
                'detail' => "{$n} pending webhook(s) older than one hour.",
            ];
        }

        if ($n = max(0, (int) ($row->pending_seller_verifications ?? 0))) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Seller verification queue',
                'detail' => "{$n} KYC submission(s) awaiting review.",
                'href' => route('admin.sellers.index'),
            ];
        }

        if ($n = max(0, (int) ($row->pending_wallet_top_ups ?? 0))) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Wallet top-up queue',
                'detail' => "{$n} manual wallet funding request(s) awaiting finance review.",
                'href' => route('admin.wallet-top-ups.index'),
            ];
        }

        if ($n = max(0, (int) ($row->escalated_seller_verifications ?? 0))) {
            $alerts[] = [
                'severity' => 'danger',
                'title' => 'Seller KYC escalations',
                'detail' => "{$n} verification case(s) breached SLA and were escalated automatically.",
                'href' => route('admin.sellers.index'),
            ];
        }

        if ($n = max(0, (int) ($row->failed_outbox ?? 0))) {
            $alerts[] = [
                'severity' => 'danger',
                'title' => 'Outbox failures',
                'detail' => "{$n} outbox event(s) in failed state.",
            ];
        }

        if ($n = max(0, (int) ($row->stuck_outbox ?? 0))) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Outbox retries pending',
                'detail' => "{$n} outbox event(s) overdue for publish retry.",
            ];
        }

        $escalatedDisputes = max(0, (int) ($row->escalated_disputes ?? 0));
        $escalatedWithdrawals = max(0, (int) ($row->escalated_withdrawals ?? 0));
        if ($escalatedDisputes > 0 || $escalatedWithdrawals > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'SLA escalations active',
                'detail' => "{$escalatedDisputes} dispute(s) and {$escalatedWithdrawals} withdrawal(s) are escalated for senior attention.",
                'href' => route('admin.disputes.index'),
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'severity' => 'success',
                'title' => 'All clear',
                'detail' => 'No automated risk signals from webhooks or outbox in this snapshot.',
            ];
        }

        return $alerts;
    }
}

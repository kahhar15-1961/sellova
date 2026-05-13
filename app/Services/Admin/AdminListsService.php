<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\RoleCodes;
use App\Models\AuditLog;
use App\Models\DisputeCase;
use App\Models\EscrowAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTopUpRequest;
use App\Models\WithdrawalRequest;
use App\Services\Promotion\PromotionService;
use Illuminate\Http\Request;

/**
 * Admin panel list views: paginated, filtered queries against production models.
 */
final class AdminListsService
{
    public function __construct(private readonly PromotionService $promotionService = new PromotionService())
    {
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, status?: string}}
     */
    public function ordersIndex(Request $request, User $viewer): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $builder = Order::query()
            ->with(['buyer:id,display_name,email'])
            ->orderByDesc('id');

        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('order_number', 'like', '%'.$q.'%')
                    ->orWhereHas('buyer', static function ($uq) use ($q): void {
                        $uq->where('email', 'like', '%'.$q.'%');
                    });
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $order) {
            $rows[] = [
                'order' => $order->order_number ?? '#'.$order->id,
                'buyer' => $order->buyer?->display_name ?? ('Buyer #'.$order->buyer_user_id),
                'buyer_email' => $order->buyer?->email,
                'gross_amount' => (string) $order->gross_amount,
                'currency' => (string) ($order->currency ?? ''),
                'total' => trim(($order->currency ?? '').' '.(string) $order->gross_amount),
                'status' => $order->status->value,
                'placed' => $order->placed_at?->toIso8601String() ?? '—',
                'href' => route('admin.orders.show', $order),
                'delete_href' => route('admin.orders.destroy', $order),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status]),
            'summary' => [
                'total' => (int) Order::query()->count(),
                'open_disputes' => (int) DisputeCase::query()->whereIn('status', ['opened', 'evidence_collection', 'under_review', 'escalated'])->count(),
                'in_escrow' => (int) Order::query()->where('status', 'paid_in_escrow')->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string}}
     */
    public function usersIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $builder = User::query()
            ->with(['roles:id,code'])
            ->orderByDesc('id');
        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('email', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%');
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $user) {
            $rows[] = [
                'row_id' => $user->id,
                'id' => (string) $user->id,
                'email' => $user->email ?? '—',
                'status' => $user->status,
                'risk' => (string) $user->risk_level,
                'roles' => $user->roles->pluck('code')->implode(', ') ?: '—',
                'buyer_profile' => 'Open buyer',
                'seller_profile' => $user->sellerProfile ? ($user->sellerProfile->display_name ?? 'Open seller') : '—',
                'created' => $user->created_at?->toIso8601String() ?? '—',
                'last_login' => $user->last_login_at?->toIso8601String() ?? '—',
                'href' => route('admin.users.show', $user),
                'buyer_href' => route('admin.buyers.show', $user),
                'seller_href' => $user->sellerProfile ? route('admin.seller-profiles.show', $user->sellerProfile) : null,
            ];
        }

        $staffRoleIds = UserRole::query()
            ->whereIn('role_id', function ($q): void {
                $q->select('id')
                    ->from('roles')
                    ->whereIn('code', [
                        RoleCodes::SuperAdmin,
                        RoleCodes::Admin,
                        RoleCodes::Adjudicator,
                        RoleCodes::FinanceAdmin,
                        RoleCodes::DisputeOfficer,
                        RoleCodes::KycReviewer,
                        RoleCodes::SupportAgent,
                    ]);
            })
            ->distinct('user_id')
            ->count('user_id');

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status]),
            'summary' => [
                'total' => (int) User::query()->count(),
                'active' => (int) User::query()->where('status', 'active')->count(),
                'suspended' => (int) User::query()->where('status', 'suspended')->count(),
                'staff' => (int) $staffRoleIds,
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, status?: string, type?: string}}
     */
    public function productsIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $type = trim((string) $request->query('type', ''));

        $builder = Product::query()
            ->with(['seller_profile:id,display_name'])
            ->orderByDesc('id');

        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($type !== '') {
            $this->applyProductTypeFilter($builder, $type);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('title', 'like', '%'.$q.'%')
                    ->orWhere('id', $q);
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $product) {
            $isInstantDelivery = $this->isInstantDeliveryProduct($product);
            $hasMissingMetadata = $product->title === null || $product->description === null || $product->category_id === null;
            $campaign = $this->promotionService->bestCatalogCampaignForProduct($product);
            $rows[] = [
                'row_id' => $product->id,
                'sku' => '#'.$product->id,
                'title' => $product->title ?? '—',
                'thumbnail_url' => $this->imageUrl($product->image_url),
                'type' => $this->displayProductType((string) ($product->product_type ?? '')),
                'is_instant_delivery' => $isInstantDelivery,
                'type_label' => $this->productTypeLabel((string) ($product->product_type ?? ''), $isInstantDelivery),
                'type_hint' => $this->productTypeHint((string) ($product->product_type ?? ''), $isInstantDelivery),
                'status' => (string) $product->status,
                'seller' => $product->seller_profile?->display_name ?? '—',
                'price' => trim(($product->currency ?? '').' '.(string) $product->base_price),
                'discount' => (float) ($product->discount_percentage ?? 0),
                'discount_label' => $product->discount_label,
                'campaign' => $campaign,
                'ops' => $hasMissingMetadata ? 'needs_attention' : 'ready',
                'updated' => $product->updated_at?->toIso8601String() ?? '—',
                'href' => route('admin.products.show', $product),
                'details_href' => route('admin.products.show', $product),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status, 'type' => $type]),
            'summary' => [
                'published' => (int) Product::query()->where('status', 'published')->count(),
                'draft' => (int) Product::query()->where('status', 'draft')->count(),
                'inactive' => (int) Product::query()->where('status', 'inactive')->count(),
                'physical' => (int) Product::query()->where('product_type', 'physical')->count(),
                'digital' => (function (): int {
                    $query = Product::query();
                    $this->applyProductTypeFilter($query, 'digital');
                    return (int) $query->count();
                })(),
                'needs_attention' => (int) Product::query()
                    ->where(static function ($q): void {
                        $q->whereNull('title')
                            ->orWhereNull('description')
                            ->orWhereNull('category_id');
                    })
                    ->count(),
            ],
        ];
    }

    private function productTypeLabel(string $type, bool $isInstantDelivery = false): string
    {
        if ($isInstantDelivery) {
            return 'Instant delivery';
        }

        return match ($type) {
            'physical' => 'Physical',
            'digital' => 'Digital',
            'instant_delivery' => 'Digital',
            'service' => 'Service',
            default => $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : '—',
        };
    }

    private function productTypeHint(string $type, bool $isInstantDelivery = false): string
    {
        if ($isInstantDelivery) {
            return 'Digital product with automatic instant fulfillment';
        }

        return match ($type) {
            'physical' => 'Requires shipping and inventory handling',
            'digital' => 'Delivered through digital proof or files',
            'instant_delivery' => 'Digital product with automatic instant fulfillment',
            'service' => 'Fulfilled through service delivery workflow',
            default => 'Product fulfillment type',
        };
    }

    private function displayProductType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'instant_delivery' => 'digital',
            'manual_delivery' => 'service',
            default => strtolower(trim($type)),
        };
    }

    private function isInstantDeliveryProduct(Product $product): bool
    {
        $type = strtolower(trim((string) ($product->product_type ?? '')));
        if (in_array($type, ['instant_delivery', 'instant'], true)) {
            return true;
        }

        $attributes = is_array($product->attributes_json) ? $product->attributes_json : [];
        if (filter_var($attributes['is_instant_delivery'] ?? false, FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $deliveryType = strtolower(trim((string) ($attributes['delivery_type'] ?? '')));
        $deliveryMode = strtolower(trim((string) ($attributes['delivery_mode'] ?? '')));
        $fulfillment = strtolower(trim((string) ($attributes['fulfillment'] ?? '')));

        return in_array($deliveryType, ['instant_delivery', 'instant'], true)
            || $deliveryMode === 'instant'
            || str_contains($fulfillment, 'instant');
    }

    private function applyProductTypeFilter($builder, string $type): void
    {
        $normalized = strtolower(trim($type));

        if ($normalized === 'instant_delivery') {
            $builder->where(static function ($query): void {
                $query->where('product_type', 'instant_delivery')
                    ->orWhere(static function ($digital): void {
                        $digital->where('product_type', 'digital')
                            ->where(static function ($flags): void {
                                $flags->where('attributes_json', 'like', '%"is_instant_delivery":true%')
                                    ->orWhere('attributes_json', 'like', '%"is_instant_delivery":"1"%')
                                    ->orWhere('attributes_json', 'like', '%"is_instant_delivery":1%')
                                    ->orWhere('attributes_json', 'like', '%"delivery_mode":"instant"%')
                                    ->orWhere('attributes_json', 'like', '%"delivery_type":"instant_delivery"%')
                                    ->orWhere('attributes_json', 'like', '%"delivery_type":"instant"%');
                            });
                    });
            });

            return;
        }

        if ($normalized === 'digital') {
            $builder->where('product_type', 'digital')
                ->where(static function ($flags): void {
                    $flags->whereNull('attributes_json')
                        ->orWhere(static function ($noInstant): void {
                            $noInstant->where('attributes_json', 'not like', '%"is_instant_delivery":true%')
                                ->where('attributes_json', 'not like', '%"is_instant_delivery":"1"%')
                                ->where('attributes_json', 'not like', '%"is_instant_delivery":1%')
                                ->where('attributes_json', 'not like', '%"delivery_mode":"instant"%')
                                ->where('attributes_json', 'not like', '%"delivery_type":"instant_delivery"%')
                                ->where('attributes_json', 'not like', '%"delivery_type":"instant"%');
                        });
                });

            return;
        }

        $builder->where('product_type', $normalized === 'service' ? 'service' : $normalized);
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
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, state?: string}}
     */
    public function escrowsIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $state = trim((string) $request->query('state', ''));

        $builder = EscrowAccount::query()
            ->with(['order:id,order_number'])
            ->orderByDesc('id');

        if ($state !== '') {
            $builder->where('state', $state);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('id', $q)
                    ->orWhereHas('order', static function ($oq) use ($q): void {
                        $oq->where('order_number', 'like', '%'.$q.'%');
                    });
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $escrow) {
            $rows[] = [
                'order' => $escrow->order?->order_number ?? '#'.$escrow->order_id,
                'state' => $escrow->state->value,
                'held' => trim(($escrow->currency ?? '').' '.(string) $escrow->held_amount),
                'currency' => (string) ($escrow->currency ?? ''),
                'order_href' => route('admin.orders.show', $escrow->order_id),
                'href' => route('admin.escrows.show', $escrow),
                'delete_href' => route('admin.escrows.destroy', $escrow),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'state' => $state]),
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string}}
     */
    public function walletsIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $builder = Wallet::query()->with(['user:id,email'])->orderByDesc('id');
        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('id', $q)
                    ->orWhereHas('user', static function ($uq) use ($q): void {
                        $uq->where('email', 'like', '%'.$q.'%');
                    });
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $wallet) {
            $latestLedger = WalletLedgerEntry::query()
                ->where('wallet_id', $wallet->id)
                ->orderByDesc('id')
                ->value('running_balance_after');
            $activeHolds = WalletHold::query()
                ->where('wallet_id', $wallet->id)
                ->where('status', 'active')
                ->sum('amount');
            $rows[] = [
                'id' => (string) $wallet->id,
                'user' => $wallet->user?->email ?? '—',
                'type' => $wallet->wallet_type->value,
                'currency' => (string) ($wallet->currency ?? ''),
                'status' => $wallet->status->value,
                'balance' => $latestLedger === null ? '—' : (string) $latestLedger,
                'holds' => (string) $activeHolds,
                'href' => route('admin.wallets.show', $wallet),
                'export_href' => route('admin.wallets.ledger-export', $wallet),
                'delete_href' => route('admin.wallets.destroy', $wallet),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status]),
            'summary' => [
                'total' => (int) Wallet::query()->count(),
                'active' => (int) Wallet::query()->where('status', 'active')->count(),
                'frozen' => (int) Wallet::query()->where('status', 'frozen')->count(),
                'closed' => (int) Wallet::query()->where('status', 'closed')->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string}}
     */
    public function auditLogsIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $actor = trim((string) $request->query('actor', ''));

        $builder = AuditLog::query()
            ->with(['actor_user:id,email'])
            ->orderByDesc('id');

        if ($actor !== '') {
            $builder->whereHas('actor_user', static function ($uq) use ($actor): void {
                $uq->where('email', 'like', '%'.$actor.'%');
            });
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('action', 'like', '%'.$q.'%')
                    ->orWhere('target_type', 'like', '%'.$q.'%')
                    ->orWhere('correlation_id', 'like', '%'.$q.'%')
                    ->orWhere('id', $q);
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $log) {
            $rows[] = [
                'time' => $log->created_at?->toIso8601String() ?? '—',
                'actor' => $log->actor_user?->email ?? '—',
                'action' => (string) ($log->action ?? ''),
                'target' => ($log->target_type ?? '').' #'.(string) $log->target_id,
                'reason' => (string) ($log->reason_code ?? '—'),
                'href' => route('admin.audit-logs.show', $log),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'actor' => $actor]),
            'summary' => [
                'total' => (int) AuditLog::query()->count(),
                'today' => (int) AuditLog::query()->whereDate('created_at', now()->toDateString())->count(),
                'admin_actions' => (int) AuditLog::query()->where('action', 'like', 'admin.%')->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, status?: string}}
     */
    public function withdrawalsIndex(Request $request, User $viewer): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $warningHours = (int) config('admin_sla.withdrawals.warning_hours', 12);
        $breachHours = (int) config('admin_sla.withdrawals.breach_hours', 24);

        $builder = WithdrawalRequest::query()
            ->with(['seller_profile:id,display_name', 'assigned_to_user:id,email'])
            ->orderByDesc('id');

        if (! $viewer->isPlatformStaff()) {
            $builder->whereHas('seller_profile', static function ($q) use ($viewer): void {
                $q->where('user_id', $viewer->id);
            });
        }
        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('id', $q)
                    ->orWhere('currency', 'like', '%'.$q.'%')
                    ->orWhereHas('seller_profile', static function ($sq) use ($q): void {
                        $sq->where('display_name', 'like', '%'.$q.'%');
                    });
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $wr) {
            $createdAt = $wr->created_at;
            $slaHours = $createdAt?->diffInHours(now()) ?? 0;
            $isBreach = $slaHours >= $breachHours;
            $rows[] = [
                'id' => $wr->id,
                'request' => '#'.$wr->id,
                'seller' => $wr->seller_profile?->display_name ?? '—',
                'amount' => trim(($wr->currency ?? '').' '.(string) $wr->requested_amount),
                'status' => $wr->status->value,
                'assignee' => $wr->assigned_to_user?->email ?? 'Unassigned',
                'is_assigned_to_me' => (int) $wr->assigned_to_user_id === (int) $viewer->id,
                'is_claimed' => $wr->assigned_to_user_id !== null,
                'sla' => $isBreach ? "Breach ({$slaHours}h)" : "{$slaHours}h",
                'sla_state' => $isBreach ? 'breach' : ($slaHours >= $warningHours ? 'warning' : 'ok'),
                'is_escalated' => $wr->escalated_at !== null,
                'requested' => $wr->created_at?->toIso8601String() ?? '—',
                'href' => route('admin.withdrawals.show', $wr),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status]),
            'summary' => [
                'requested' => (int) WithdrawalRequest::query()->where('status', 'requested')->count(),
                'under_review' => (int) WithdrawalRequest::query()->where('status', 'under_review')->count(),
                'paid_out' => (int) WithdrawalRequest::query()->where('status', 'paid_out')->count(),
                'escalated' => (int) WithdrawalRequest::query()
                    ->whereIn('status', ['requested', 'under_review'])
                    ->whereNotNull('escalated_at')
                    ->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{status?: string}}
     */
    public function walletTopUpRequestsIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $status = trim((string) $request->query('status', ''));

        $builder = WalletTopUpRequest::query()
            ->with(['wallet.user:id,email', 'reviewed_by_user:id,email'])
            ->orderByDesc('id');

        if ($status !== '') {
            $builder->where('status', $status);
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $requestRow) {
            $paymentLabel = match ($requestRow->payment_method) {
                'bkash' => 'bKash',
                'nagad' => 'Nagad',
                'bank' => 'Bank',
                default => '—',
            };
            $rows[] = [
                'id' => $requestRow->id,
                'request' => '#'.$requestRow->id,
                'user' => $requestRow->wallet?->user?->email ?? '—',
                'wallet' => $requestRow->wallet === null ? '—' : '#'.$requestRow->wallet->id.' · '.$requestRow->wallet->currency,
                'amount' => trim(($requestRow->currency ?? '').' '.(string) $requestRow->requested_amount),
                'payment' => $paymentLabel.($requestRow->payment_reference ? ' · '.$requestRow->payment_reference : ''),
                'status' => $requestRow->status->value,
                'reviewer' => $requestRow->reviewed_by_user_id ? ($requestRow->reviewed_by_user?->email ?? '—') : 'Pending',
                'created' => $requestRow->created_at?->toIso8601String() ?? '—',
                'href' => route('admin.wallet-top-ups.show', $requestRow),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['status' => $status]),
            'summary' => [
                'requested' => (int) WalletTopUpRequest::query()->where('status', 'requested')->count(),
                'approved' => (int) WalletTopUpRequest::query()->where('status', 'approved')->count(),
                'rejected' => (int) WalletTopUpRequest::query()->where('status', 'rejected')->count(),
                'failed' => (int) WalletTopUpRequest::query()->where('status', 'failed')->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, status?: string}}
     */
    public function disputesIndex(Request $request, User $viewer): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $warningHours = (int) config('admin_sla.disputes.warning_hours', 24);
        $breachHours = (int) config('admin_sla.disputes.breach_hours', 48);

        $builder = DisputeCase::query()
            ->with(['order:id,order_number', 'assigned_to_user:id,email'])
            ->orderByDesc('id');

        if (! $viewer->isPlatformStaff()) {
            $uid = $viewer->id;
            $builder->where(function ($w) use ($uid): void {
                $w->where('opened_by_user_id', $uid)
                    ->orWhereHas('order', function ($oq) use ($uid): void {
                        $oq->where('buyer_user_id', $uid)
                            ->orWhereHas('orderItems.seller_profile', static function ($sp) use ($uid): void {
                                $sp->where('user_id', $uid);
                            });
                    });
            });
        }
        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('id', $q)
                    ->orWhereHas('order', static function ($oq) use ($q): void {
                        $oq->where('order_number', 'like', '%'.$q.'%');
                    });
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $case) {
            $openedAt = $case->opened_at ?? $case->created_at;
            $slaHours = $openedAt?->diffInHours(now()) ?? 0;
            $isBreach = $slaHours >= $breachHours;
            $rows[] = [
                'id' => $case->id,
                'case' => '#'.$case->id,
                'order' => $case->order?->order_number ?? '#'.$case->order_id,
                'stage' => $case->status->value,
                'assignee' => $case->assigned_to_user?->email ?? 'Unassigned',
                'is_assigned_to_me' => (int) $case->assigned_to_user_id === (int) $viewer->id,
                'is_claimed' => $case->assigned_to_user_id !== null,
                'sla' => $isBreach ? "Breach ({$slaHours}h)" : "{$slaHours}h",
                'sla_state' => $isBreach ? 'breach' : ($slaHours >= $warningHours ? 'warning' : 'ok'),
                'is_escalated' => $case->escalated_at !== null,
                'href' => route('admin.disputes.show', ['dispute' => $case->id]),
                'order_href' => route('admin.orders.show', ['order' => $case->order_id]),
                'delete_href' => route('admin.disputes.destroy', ['dispute' => $case->id]),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status]),
            'summary' => [
                'opened' => (int) DisputeCase::query()->where('status', 'opened')->count(),
                'under_review' => (int) DisputeCase::query()->where('status', 'under_review')->count(),
                'resolved' => (int) DisputeCase::query()->where('status', 'resolved')->count(),
                'escalated' => (int) DisputeCase::query()
                    ->where('status', '!=', 'resolved')
                    ->whereNotNull('escalated_at')
                    ->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, status?: string}}
     */
    public function buyersIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $builder = User::query()
            ->whereDoesntHave('sellerProfile')
            ->orderByDesc('id');
        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('email', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%');
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $buyer) {
            $ordersCount = (int) $buyer->orders()->count();
            $disputesCount = (int) DisputeCase::query()
                ->whereHas('order', static fn ($q) => $q->where('buyer_user_id', $buyer->id))
                ->count();
            $rows[] = [
                'buyer' => '#'.$buyer->id,
                'name' => $buyer->display_name ?? 'Buyer #'.$buyer->id,
                'email' => $buyer->email ?? '—',
                'status' => $buyer->status,
                'risk' => $buyer->risk_level,
                'user' => trim((string) (($buyer->display_name ?? 'Buyer #'.$buyer->id).' · '.($buyer->email ?? 'No email'))),
                'orders' => (string) $ordersCount,
                'disputes' => (string) $disputesCount,
                'href' => route('admin.buyers.show', $buyer),
                'user_href' => route('admin.users.show', $buyer),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status]),
            'summary' => [
                'total' => (int) User::query()->whereDoesntHave('sellerProfile')->count(),
                'active' => (int) User::query()->whereDoesntHave('sellerProfile')->where('status', 'active')->count(),
                'high_risk' => (int) User::query()->whereDoesntHave('sellerProfile')->where('risk_level', 'high')->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, verification?: string}}
     */
    public function sellerProfilesIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $verification = trim((string) $request->query('verification', ''));

        $builder = SellerProfile::query()
            ->with(['user:id,email'])
            ->orderByDesc('id');
        if ($verification !== '') {
            $builder->where('verification_status', $verification);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('display_name', 'like', '%'.$q.'%')
                    ->orWhereHas('user', static fn ($uq) => $uq->where('email', 'like', '%'.$q.'%'));
            });
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $seller) {
            $productCount = (int) Product::query()->where('seller_profile_id', $seller->id)->count();
            $pendingWithdrawals = (int) WithdrawalRequest::query()
                ->where('seller_profile_id', $seller->id)
                ->whereIn('status', ['requested', 'under_review'])
                ->count();
            $rows[] = [
                'seller' => '#'.$seller->id,
                'display_name' => $seller->display_name ?? '—',
                'account' => $seller->user?->email ?? '—',
                'verification' => (string) $seller->verification_status,
                'store' => (string) $seller->store_status,
                'products' => (string) $productCount,
                'pending_withdrawals' => (string) $pendingWithdrawals,
                'href' => route('admin.seller-profiles.show', $seller),
                'user_href' => $seller->user ? route('admin.users.show', $seller->user) : null,
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'verification' => $verification]),
            'summary' => [
                'total' => (int) SellerProfile::query()->count(),
                'verified' => (int) SellerProfile::query()->where('verification_status', 'verified')->count(),
                'pending' => (int) SellerProfile::query()->whereIn('verification_status', ['pending', 'under_review'])->count(),
            ],
        ];
    }

    /**
     * @return array{page: int, perPage: int}
     */
    private function pagination(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        return [$page, $perPage];
    }

    /**
     * @return array{page: int, perPage: int, total: int, lastPage: int}
     */
    private function paginationPayload(int $page, int $perPage, int $total): array
    {
        $lastPage = max(1, (int) ceil(max(1, $total) / $perPage));

        return [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => $lastPage,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\RoleCodes;
use App\Models\AuditLog;
use App\Models\DisputeCase;
use App\Models\EscrowAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

/**
 * Admin panel list views: paginated, filtered queries against production models.
 */
final class AdminListsService
{
    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, status?: string}}
     */
    public function ordersIndex(Request $request, User $viewer): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $builder = Order::query()
            ->with(['buyer:id,email'])
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
            $buyerEmail = $order->buyer?->email;
            $rows[] = [
                'order' => $order->order_number ?? '#'.$order->id,
                'buyer' => $buyerEmail ?? '—',
                'total' => trim(($order->currency ?? '').' '.(string) $order->gross_amount),
                'status' => $order->status->value,
                'placed' => $order->placed_at?->toIso8601String() ?? '—',
                'href' => route('admin.orders.show', $order),
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
                'created' => $user->created_at?->toIso8601String() ?? '—',
                'last_login' => $user->last_login_at?->toIso8601String() ?? '—',
                'href' => route('admin.users.show', $user),
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
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{q?: string, status?: string}}
     */
    public function productsIndex(Request $request): array
    {
        [$page, $perPage] = $this->pagination($request);
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $builder = Product::query()
            ->with(['seller_profile:id,display_name'])
            ->orderByDesc('id');

        if ($status !== '') {
            $builder->where('status', $status);
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
            $rows[] = [
                'row_id' => $product->id,
                'sku' => '#'.$product->id,
                'title' => $product->title ?? '—',
                'status' => (string) $product->status,
                'seller' => $product->seller_profile?->display_name ?? '—',
                'price' => trim(($product->currency ?? '').' '.(string) $product->base_price),
                'updated' => $product->updated_at?->toIso8601String() ?? '—',
                'href' => route('admin.products.show', $product),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['q' => $q, 'status' => $status]),
            'summary' => [
                'published' => (int) Product::query()->where('status', 'published')->count(),
                'draft' => (int) Product::query()->where('status', 'draft')->count(),
                'inactive' => (int) Product::query()->where('status', 'inactive')->count(),
            ],
        ];
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
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{status?: string}}
     */
    public function withdrawalsIndex(Request $request, User $viewer): array
    {
        [$page, $perPage] = $this->pagination($request);
        $status = trim((string) $request->query('status', ''));

        $builder = WithdrawalRequest::query()
            ->with(['seller_profile:id,display_name'])
            ->orderByDesc('id');

        if (! $viewer->isPlatformStaff()) {
            $builder->whereHas('seller_profile', static function ($q) use ($viewer): void {
                $q->where('user_id', $viewer->id);
            });
        }
        if ($status !== '') {
            $builder->where('status', $status);
        }

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $wr) {
            $rows[] = [
                'request' => '#'.$wr->id,
                'seller' => $wr->seller_profile?->display_name ?? '—',
                'amount' => trim(($wr->currency ?? '').' '.(string) $wr->requested_amount),
                'status' => $wr->status->value,
                'requested' => $wr->created_at?->toIso8601String() ?? '—',
                'href' => route('admin.withdrawals.show', $wr),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['status' => $status]),
            'summary' => [
                'requested' => (int) WithdrawalRequest::query()->where('status', 'requested')->count(),
                'under_review' => (int) WithdrawalRequest::query()->where('status', 'under_review')->count(),
                'paid_out' => (int) WithdrawalRequest::query()->where('status', 'paid_out')->count(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, lastPage: int}, filters: array{status?: string}}
     */
    public function disputesIndex(Request $request, User $viewer): array
    {
        [$page, $perPage] = $this->pagination($request);
        $status = trim((string) $request->query('status', ''));

        $builder = DisputeCase::query()
            ->with(['order:id,order_number'])
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

        $total = (int) $builder->count();
        $rows = [];
        foreach ((clone $builder)->forPage($page, $perPage)->get() as $case) {
            $rows[] = [
                'case' => '#'.$case->id,
                'order' => $case->order?->order_number ?? '#'.$case->order_id,
                'stage' => $case->status->value,
                'href' => route('admin.disputes.show', ['dispute' => $case->id]),
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $this->paginationPayload($page, $perPage, $total),
            'filters' => array_filter(['status' => $status]),
            'summary' => [
                'opened' => (int) DisputeCase::query()->where('status', 'opened')->count(),
                'under_review' => (int) DisputeCase::query()->where('status', 'under_review')->count(),
                'resolved' => (int) DisputeCase::query()->where('status', 'resolved')->count(),
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

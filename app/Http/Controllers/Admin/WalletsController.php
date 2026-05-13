<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\WalletAccountStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Audit\AuditLogWriter;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class WalletsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->walletsIndex($request);

        return Inertia::render('Admin/Wallets/Index', [
            'header' => $this->pageHeader(
                'Wallets & ledger',
                'Wallet operations view with quick balances and hold totals.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Finance'],
                    ['label' => 'Wallets'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.wallets.index'),
            'top_up_requests_url' => route('admin.wallet-top-ups.index'),
            'status_options' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'frozen', 'label' => 'Frozen'],
                ['value' => 'closed', 'label' => 'Closed'],
            ],
        ]);
    }

    public function destroy(Request $request, Wallet $wallet): RedirectResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        if ($actor === null || (! $actor->isPlatformStaff() && ! $actor->hasPermissionCode(AdminPermission::WALLETS_MANAGE))) {
            abort(403);
        }

        $before = [
            'id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'wallet_type' => $wallet->wallet_type->value,
            'currency' => $wallet->currency,
            'status' => $wallet->status instanceof WalletAccountStatus ? $wallet->status->value : (string) $wallet->status,
        ];

        DB::transaction(function () use ($wallet, $actor, $request, $before): void {
            /** @var Wallet $locked */
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $walletId = (int) $locked->id;

            $this->deleteRelatedWalletRecords($walletId);
            $locked->delete();

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.wallet.deleted',
                targetType: 'wallet',
                targetId: $walletId,
                beforeJson: $before,
                afterJson: ['deleted' => true],
                reasonCode: 'wallet_management',
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return redirect()->route('admin.wallets.index')->with('success', 'Wallet deleted.');
    }

    private function deleteRelatedWalletRecords(int $walletId): void
    {
        $ledgerEntryIds = $this->idsFor('wallet_ledger_entries', 'wallet_id', $walletId);
        $withdrawalRequestIds = $this->idsFor('withdrawal_requests', 'wallet_id', $walletId);

        $this->deleteWhereIn('withdrawal_transactions', 'withdrawal_request_id', $withdrawalRequestIds);
        $this->deleteWhereIn('withdrawal_requests', 'id', $withdrawalRequestIds);
        $this->deleteWhereIn('wallet_top_up_requests', 'wallet_id', [$walletId]);
        $this->deleteWhereIn('wallet_balance_snapshots', 'wallet_id', [$walletId]);

        $this->updateWhereIn('wallet_ledger_entries', 'reversal_of_entry_id', $ledgerEntryIds, ['reversal_of_entry_id' => null]);
        $this->updateWhereIn('wallet_ledger_entries', 'counterparty_wallet_id', [$walletId], ['counterparty_wallet_id' => null]);
        $this->deleteWhereIn('wallet_ledger_entries', 'wallet_id', [$walletId]);
        $this->deleteWhereIn('wallet_holds', 'wallet_id', [$walletId]);
    }

    /**
     * @return list<int>
     */
    private function idsFor(string $table, string $column, int $value): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->where($column, $value)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $values
     */
    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        if ($values === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->delete();
    }

    /**
     * @param  list<int>  $values
     * @param  array<string, mixed>  $updates
     */
    private function updateWhereIn(string $table, string $column, array $values, array $updates): void
    {
        if ($values === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->update($updates);
    }
}

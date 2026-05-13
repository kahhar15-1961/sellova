<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\EscrowState;
use App\Models\EscrowAccount;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class EscrowsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->escrowsIndex($request);

        return Inertia::render('Admin/Escrows/Index', [
            'header' => $this->pageHeader(
                'Escrows',
                'Held funds, settlement controls, and order-linked escrow operations.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders & Escrow'],
                    ['label' => 'Escrows'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.escrows.index'),
            'state_options' => collect(EscrowState::cases())->map(static fn (EscrowState $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }

    public function destroy(Request $request, EscrowAccount $escrow): RedirectResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        if ($actor === null || (! $actor->isPlatformStaff() && ! $actor->hasPermissionCode(AdminPermission::ESCROWS_MANAGE))) {
            abort(403);
        }

        $before = [
            'id' => $escrow->id,
            'order_id' => $escrow->order_id,
            'state' => $escrow->state instanceof EscrowState ? $escrow->state->value : (string) $escrow->state,
            'held_amount' => (string) $escrow->held_amount,
            'released_amount' => (string) $escrow->released_amount,
            'refunded_amount' => (string) $escrow->refunded_amount,
            'currency' => $escrow->currency,
        ];

        DB::transaction(function () use ($escrow, $actor, $request, $before): void {
            /** @var EscrowAccount $locked */
            $locked = EscrowAccount::query()->lockForUpdate()->findOrFail($escrow->id);
            $escrowId = (int) $locked->id;

            $this->deleteWhere('escrow_timeout_events', 'escrow_account_id', $escrowId);
            $this->deleteWhere('escrow_events', 'escrow_account_id', $escrowId);
            $locked->delete();

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.escrow.deleted',
                targetType: 'escrow',
                targetId: $escrowId,
                beforeJson: $before,
                afterJson: ['deleted' => true],
                reasonCode: 'escrow_management',
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return redirect()->route('admin.escrows.index')->with('success', 'Escrow deleted.');
    }

    private function deleteWhere(string $table, string $column, int $value): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, $value)->delete();
    }
}

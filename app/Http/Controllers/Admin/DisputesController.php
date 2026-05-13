<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\DisputeCaseStatus;
use App\Models\DisputeCase;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class DisputesController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->lists->disputesIndex($request, $user);

        return Inertia::render('Admin/Disputes/Index', [
            'header' => $this->pageHeader(
                'Disputes',
                'Case queue with order linkage; open a case to move to review or resolve when escrow is under dispute.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Support'],
                    ['label' => 'Disputes'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.disputes.index'),
            'claim_url_template' => route('admin.disputes.claim', ['dispute' => '__ID__']),
            'unclaim_url_template' => route('admin.disputes.unclaim', ['dispute' => '__ID__']),
            'status_options' => collect(DisputeCaseStatus::cases())->map(static fn (DisputeCaseStatus $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }

    public function destroy(Request $request, DisputeCase $dispute): RedirectResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        if ($actor === null || (! $actor->isPlatformStaff() && ! $actor->hasPermissionCode(AdminPermission::DISPUTES_RESOLVE))) {
            abort(403);
        }

        $before = [
            'id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'order_item_id' => $dispute->order_item_id,
            'opened_by_user_id' => $dispute->opened_by_user_id,
            'assigned_to_user_id' => $dispute->assigned_to_user_id,
            'status' => $dispute->status instanceof DisputeCaseStatus ? $dispute->status->value : (string) $dispute->status,
            'resolution_outcome' => $dispute->resolution_outcome?->value,
        ];

        DB::transaction(function () use ($dispute, $actor, $request, $before): void {
            /** @var DisputeCase $locked */
            $locked = DisputeCase::query()->lockForUpdate()->findOrFail($dispute->id);
            $disputeId = (int) $locked->id;

            $this->deleteWhere('dispute_decisions', 'dispute_case_id', $disputeId);
            $this->deleteWhere('dispute_evidences', 'dispute_case_id', $disputeId);
            $locked->delete();

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.dispute.deleted',
                targetType: 'dispute',
                targetId: $disputeId,
                beforeJson: $before,
                afterJson: ['deleted' => true],
                reasonCode: 'dispute_management',
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return redirect()->route('admin.disputes.index')->with('success', 'Dispute deleted.');
    }

    private function deleteWhere(string $table, string $column, int $value): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, $value)->delete();
    }
}

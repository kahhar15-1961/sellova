<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AdminActionApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdminApprovalsInboxController extends AdminPageController
{
    public function __invoke(Request $request): Response
    {
        $status = trim((string) $request->query('status', 'pending'));
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $q = trim((string) $request->query('q', ''));
        $selectedId = (int) $request->query('approval_id', 0);

        $builder = AdminActionApproval::query()
            ->with(['requested_by_user:id,email', 'approved_by_user:id,email'])
            ->orderByDesc('id');

        if ($status !== 'all') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('action_code', 'like', '%'.$q.'%')
                    ->orWhere('target_type', 'like', '%'.$q.'%')
                    ->orWhere('reason_code', 'like', '%'.$q.'%');
            });
        }

        $approvals = $builder->limit(100)->get();
        $selected = $selectedId > 0
            ? $approvals->firstWhere('id', $selectedId)
            : $approvals->first();

        if ($selected !== null) {
            $selected->load(['messages.author_user:id,email', 'threadReads.user:id,email']);
        }

        return Inertia::render('Admin/Approvals/Index', [
            'header' => $this->pageHeader(
                'Approvals Inbox',
                'Dual-approval queue with collaborative chat and fast governance decisions.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Approvals'],
                ],
            ),
            'filters' => ['status' => $status, 'q' => $q],
            'index_url' => route('admin.approvals.index'),
            'approvals' => $approvals->map(static fn (AdminActionApproval $a): array => [
                'id' => $a->id,
                'action_code' => $a->action_code,
                'target_type' => $a->target_type,
                'target_id' => $a->target_id,
                'status' => $a->status,
                'reason_code' => $a->reason_code,
                'requested_by' => $a->requested_by_user?->email ?? '—',
                'approved_by' => $a->approved_by_user?->email,
                'requested_at' => $a->requested_at?->toIso8601String(),
            ])->values()->all(),
            'selected' => $selected ? [
                'id' => $selected->id,
                'action_code' => $selected->action_code,
                'target_type' => $selected->target_type,
                'target_id' => $selected->target_id,
                'status' => $selected->status,
                'reason_code' => $selected->reason_code,
                'decision_reason' => $selected->decision_reason,
                'requested_by' => $selected->requested_by_user?->email ?? '—',
                'approved_by' => $selected->approved_by_user?->email,
                'requested_at' => $selected->requested_at?->toIso8601String(),
                'decided_at' => $selected->decided_at?->toIso8601String(),
                'proposed_payload_json' => $selected->proposed_payload_json,
                'decision_url' => route('admin.action-approvals.decide', $selected),
                'message_url' => route('admin.action-approvals.messages.store', $selected),
                'messages_api_url' => route('admin.action-approvals.messages.index', $selected),
                'typing_url' => route('admin.action-approvals.realtime.typing', $selected),
                'read_url' => route('admin.action-approvals.realtime.read', $selected),
            ] : null,
            'messages' => $selected
                ? $selected->messages->sortBy('id')->values()->map(static fn ($m): array => [
                    'id' => $m->id,
                    'author_user_id' => $m->author_user_id,
                    'author' => $m->author_user?->email ?? '—',
                    'message' => $m->message,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'delivered_at' => $m->delivered_at?->toIso8601String(),
                ])->all()
                : [],
            'thread_reads' => $selected
                ? $selected->threadReads->map(static fn ($r): array => [
                    'user_id' => $r->user_id,
                    'last_read_message_id' => $r->last_read_message_id,
                    'reader_name' => $r->user?->email ?? '—',
                ])->values()->all()
                : [],
            'required_reader_ids' => $selected ? $selected->requiredReaderUserIds() : [],
        ]);
    }
}

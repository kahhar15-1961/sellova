<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\AdminApprovalMessageCreated;
use App\Http\Requests\Admin\StoreAdminApprovalMessageRequest;
use App\Models\AdminActionApproval;
use App\Models\AdminActionApprovalMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class AdminApprovalMessageController
{
    public function index(AdminActionApproval $approval): JsonResponse
    {
        $approval->load(['messages.author_user:id,email', 'threadReads.user:id,email']);

        return response()->json([
            'messages' => $approval->messages
                ->sortBy('id')
                ->values()
                ->map(static fn ($m): array => [
                    'id' => $m->id,
                    'author_user_id' => $m->author_user_id,
                    'author' => $m->author_user?->email ?? '—',
                    'message' => $m->message,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'delivered_at' => $m->delivered_at?->toIso8601String(),
                ])->all(),
            'thread_reads' => $approval->threadReads
                ->map(static fn ($r): array => [
                    'user_id' => $r->user_id,
                    'last_read_message_id' => $r->last_read_message_id,
                    'reader_name' => $r->user?->email ?? '—',
                ])->values()->all(),
            'required_reader_ids' => $approval->requiredReaderUserIds(),
        ]);
    }

    public function store(StoreAdminApprovalMessageRequest $request, AdminActionApproval $approval): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $now = now();

        $message = AdminActionApprovalMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'approval_id' => $approval->id,
            'author_user_id' => $actor->id,
            'message' => (string) $request->validated('message'),
            'created_at' => $now,
            'delivered_at' => $now,
        ]);

        event(new AdminApprovalMessageCreated(
            approvalId: $approval->id,
            message: [
                'id' => $message->id,
                'author_user_id' => $actor->id,
                'author' => $actor->email ?? '—',
                'message' => $message->message,
                'created_at' => $message->created_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
            ],
        ));

        return redirect()->route('admin.approvals.index', ['approval_id' => $approval->id])->with('success', 'Message sent.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\AdminApprovalReadUpdated;
use App\Events\AdminApprovalUserTyping;
use App\Http\Requests\Admin\UpdateAdminApprovalReadRequest;
use App\Http\Requests\Admin\UpdateAdminApprovalTypingRequest;
use App\Models\AdminActionApproval;
use App\Models\AdminActionApprovalThreadRead;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class AdminApprovalRealtimeController
{
    public function typing(UpdateAdminApprovalTypingRequest $request, AdminActionApproval $approval): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $name = $actor->email ?? ('User #'.$actor->id);

        event(new AdminApprovalUserTyping(
            approvalId: $approval->id,
            userId: $actor->id,
            name: $name,
            typing: (bool) $request->validated('typing'),
        ));

        return response()->json(['ok' => true]);
    }

    public function read(UpdateAdminApprovalReadRequest $request, AdminActionApproval $approval): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $lastId = (int) $request->validated('last_read_message_id');

        $row = AdminActionApprovalThreadRead::query()->firstOrNew([
            'approval_id' => $approval->id,
            'user_id' => $actor->id,
        ]);

        if ($row->exists && (int) $row->last_read_message_id >= $lastId) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $row->last_read_message_id = $lastId;
        $row->save();

        $readerName = $actor->email ?? ('User #'.$actor->id);

        event(new AdminApprovalReadUpdated(
            approvalId: $approval->id,
            userId: $actor->id,
            readerName: $readerName,
            lastReadMessageId: $lastId,
        ));

        return response()->json(['ok' => true]);
    }
}

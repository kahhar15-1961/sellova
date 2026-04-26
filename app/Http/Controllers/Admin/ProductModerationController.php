<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ModerateProductRequest;
use App\Models\AdminActionApproval;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class ProductModerationController
{
    public function updateStatus(ModerateProductRequest $request, Product $product): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $before = [
            'status' => $product->status,
            'published_at' => $product->published_at?->toIso8601String(),
        ];
        $policyCode = (string) $request->validated('policy_code');
        $reason = (string) $request->validated('reason');
        $evidenceNotes = $request->validated('evidence_notes');
        $newStatus = (string) $request->validated('status');

        $needsDualApproval = in_array($policyCode, ['policy_violation', 'counterfeit_risk'], true) || $newStatus === 'archived';
        if ($needsDualApproval) {
            AdminActionApproval::query()->create([
                'uuid' => (string) Str::uuid(),
                'action_code' => 'product.moderation_update',
                'target_type' => 'product',
                'target_id' => $product->id,
                'proposed_payload_json' => [
                    'status' => $newStatus,
                    'policy_code' => $policyCode,
                    'evidence_notes' => $evidenceNotes,
                ],
                'requested_by_user_id' => $actor->id,
                'status' => 'pending',
                'reason_code' => $policyCode.':'.$reason,
                'requested_at' => now(),
            ]);

            return redirect()->route('admin.products.show', $product)->with('success', 'Moderation request submitted for dual approval.');
        }

        $product->status = $newStatus;
        if ($newStatus === 'published' && $product->published_at === null) {
            $product->published_at = now();
        }
        if ($newStatus !== 'published') {
            $product->published_at = null;
        }
        $product->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.product.status_updated',
            targetType: 'product',
            targetId: $product->id,
            beforeJson: $before,
            afterJson: [
                'status' => $product->status,
                'published_at' => $product->published_at?->toIso8601String(),
                'policy_code' => $policyCode,
                'evidence_notes' => $evidenceNotes,
            ],
            reasonCode: $policyCode.':'.$reason,
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Product moderation status updated.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\DecisionAdminActionApprovalRequest;
use App\Models\AdminActionApproval;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;

final class AdminActionApprovalController
{
    public function decide(DecisionAdminActionApprovalRequest $request, AdminActionApproval $approval): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($approval->status !== 'pending') {
            return back()->with('error', 'This request is already decided.');
        }

        if ((int) $approval->requested_by_user_id === (int) $actor->id) {
            return back()->with('error', 'Dual approval requires a different reviewer.');
        }

        $decision = (string) $request->validated('decision');
        $approval->approved_by_user_id = $actor->id;
        $approval->decided_at = now();
        $approval->decision_reason = (string) $request->validated('decision_reason');

        if ($decision === 'reject') {
            $approval->status = 'rejected';
            $approval->save();

            return back()->with('success', 'Approval request rejected.');
        }

        $payload = (array) $approval->proposed_payload_json;
        if ($approval->target_type === 'user') {
            $user = User::query()->findOrFail($approval->target_id);
            $before = ['status' => $user->status, 'risk_level' => $user->risk_level, 'restricted_checkout' => (bool) $user->restricted_checkout];
            $user->status = (string) ($payload['status'] ?? $user->status);
            $user->risk_level = (string) ($payload['risk_level'] ?? $user->risk_level);
            $user->restricted_checkout = (bool) ($payload['restricted_checkout'] ?? $user->restricted_checkout);
            $user->save();

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.buyer.risk_controls_approved',
                targetType: 'user',
                targetId: $user->id,
                beforeJson: $before,
                afterJson: ['status' => $user->status, 'risk_level' => $user->risk_level, 'restricted_checkout' => (bool) $user->restricted_checkout],
                reasonCode: (string) ($approval->reason_code ?? 'approval'),
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        if ($approval->target_type === 'seller_profile') {
            $seller = SellerProfile::query()->findOrFail($approval->target_id);
            $before = ['store_status' => (string) $seller->store_status];
            $seller->store_status = (string) ($payload['store_status'] ?? $seller->store_status);
            $seller->save();

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.seller.store_state_approved',
                targetType: 'seller_profile',
                targetId: $seller->id,
                beforeJson: $before,
                afterJson: ['store_status' => (string) $seller->store_status],
                reasonCode: (string) ($approval->reason_code ?? 'approval'),
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        if ($approval->target_type === 'product') {
            $product = Product::query()->findOrFail($approval->target_id);
            $before = ['status' => (string) $product->status, 'published_at' => $product->published_at?->toIso8601String()];
            $newStatus = (string) ($payload['status'] ?? $product->status);
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
                action: 'admin.product.status_approved',
                targetType: 'product',
                targetId: $product->id,
                beforeJson: $before,
                afterJson: ['status' => (string) $product->status, 'published_at' => $product->published_at?->toIso8601String()],
                reasonCode: (string) ($approval->reason_code ?? 'approval'),
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        $approval->status = 'approved';
        $approval->save();

        return back()->with('success', 'Approval request approved and executed.');
    }
}

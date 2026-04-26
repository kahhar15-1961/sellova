<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateBuyerRiskRequest;
use App\Models\AdminActionApproval;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class BuyerRiskController
{
    public function update(UpdateBuyerRiskRequest $request, User $buyer): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $before = [
            'status' => $buyer->status,
            'risk_level' => $buyer->risk_level,
            'restricted_checkout' => (bool) $buyer->restricted_checkout,
        ];

        $buyer->status = (string) $request->validated('status');
        $buyer->risk_level = (string) $request->validated('risk_level');
        $restrictedCheckout = (bool) $request->boolean('restricted_checkout');
        $reasonCode = (string) $request->validated('reason_code');
        $reason = (string) $request->validated('reason');

        $needsDualApproval = $buyer->risk_level === 'high' || $buyer->status !== 'active' || $restrictedCheckout;
        if ($needsDualApproval) {
            AdminActionApproval::query()->create([
                'uuid' => (string) Str::uuid(),
                'action_code' => 'buyer.risk_controls_update',
                'target_type' => 'user',
                'target_id' => $buyer->id,
                'proposed_payload_json' => [
                    'status' => $buyer->status,
                    'risk_level' => $buyer->risk_level,
                    'restricted_checkout' => $restrictedCheckout,
                ],
                'requested_by_user_id' => $actor->id,
                'status' => 'pending',
                'reason_code' => $reasonCode.':'.$reason,
                'requested_at' => now(),
            ]);

            return redirect()->route('admin.buyers.show', $buyer)->with('success', 'High-risk change submitted for dual approval.');
        }

        $buyer->restricted_checkout = $restrictedCheckout;
        $buyer->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.buyer.risk_controls_updated',
            targetType: 'user',
            targetId: $buyer->id,
            beforeJson: $before,
            afterJson: [
                'status' => $buyer->status,
                'risk_level' => $buyer->risk_level,
                'restricted_checkout' => (bool) $buyer->restricted_checkout,
            ],
            reasonCode: $reasonCode.':'.$reason,
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.buyers.show', $buyer)->with('success', 'Buyer fraud controls updated.');
    }
}

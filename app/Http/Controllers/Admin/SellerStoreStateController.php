<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateSellerStoreStateRequest;
use App\Models\AdminActionApproval;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class SellerStoreStateController
{
    public function update(UpdateSellerStoreStateRequest $request, SellerProfile $sellerProfile): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $before = [
            'store_status' => (string) $sellerProfile->store_status,
        ];

        $newStoreStatus = (string) $request->validated('store_status');
        $reasonCode = (string) $request->validated('reason_code');
        $reason = (string) $request->validated('reason');

        if ($newStoreStatus === 'suspended') {
            AdminActionApproval::query()->create([
                'uuid' => (string) Str::uuid(),
                'action_code' => 'seller.store_state_update',
                'target_type' => 'seller_profile',
                'target_id' => $sellerProfile->id,
                'proposed_payload_json' => ['store_status' => $newStoreStatus],
                'requested_by_user_id' => $actor->id,
                'status' => 'pending',
                'reason_code' => $reasonCode.':'.$reason,
                'requested_at' => now(),
            ]);

            return redirect()->route('admin.seller-profiles.show', $sellerProfile)->with('success', 'Suspension submitted for dual approval.');
        }

        $sellerProfile->store_status = $newStoreStatus;
        $sellerProfile->save();

        AuditLogWriter::write(
            actorUserId: $actor->id,
            action: 'admin.seller.store_state_updated',
            targetType: 'seller_profile',
            targetId: $sellerProfile->id,
            beforeJson: $before,
            afterJson: [
                'store_status' => (string) $sellerProfile->store_status,
            ],
            reasonCode: $reasonCode.':'.$reason,
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.seller-profiles.show', $sellerProfile)->with('success', 'Seller store state updated.');
    }
}

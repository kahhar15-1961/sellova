<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WithdrawalRequest;

final class WithdrawalRequestResource
{
    /**
     * @return array<string, mixed>
     */
    public static function detail(WithdrawalRequest $w): array
    {
        return [
            'id' => $w->id,
            'uuid' => $w->uuid,
            'idempotency_key' => $w->idempotency_key,
            'seller_profile_id' => $w->seller_profile_id,
            'wallet_id' => $w->wallet_id,
            'status' => $w->status->value,
            'requested_amount' => (string) $w->requested_amount,
            'fee_amount' => (string) $w->fee_amount,
            'net_payout_amount' => (string) $w->net_payout_amount,
            'currency' => $w->currency,
            'hold_id' => $w->hold_id,
            'reviewed_by' => $w->reviewed_by,
            'reviewed_at' => $w->reviewed_at?->toIso8601String(),
            'reject_reason' => $w->reject_reason,
            'created_at' => $w->created_at?->toIso8601String(),
            'updated_at' => $w->updated_at?->toIso8601String(),
        ];
    }
}

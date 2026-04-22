<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DisputeCase;

final class DisputeCaseResource
{
    /**
     * @return array<string, mixed>
     */
    public static function detail(DisputeCase $case): array
    {
        return [
            'id' => $case->id,
            'uuid' => $case->uuid,
            'order_id' => $case->order_id,
            'order_item_id' => $case->order_item_id,
            'opened_by_user_id' => $case->opened_by_user_id,
            'status' => $case->status->value,
            'resolution_outcome' => $case->resolution_outcome?->value,
            'opened_at' => $case->opened_at?->toIso8601String(),
            'resolved_at' => $case->resolved_at?->toIso8601String(),
            'resolution_notes' => $case->resolution_notes,
            'created_at' => $case->created_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
        ];
    }
}

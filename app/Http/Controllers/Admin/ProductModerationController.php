<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ModerateProductRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;

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

        $newStatus = (string) $request->validated('status');
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
            ],
            reasonCode: $request->validated('reason'),
            correlationId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Product moderation status updated.');
    }
}

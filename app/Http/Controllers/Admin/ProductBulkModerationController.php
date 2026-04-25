<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\BulkProductModerationRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Http\RedirectResponse;

final class ProductBulkModerationController
{
    public function updateStatus(BulkProductModerationRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $selectAll = (bool) ($validated['select_all'] ?? false);
        $ids = array_values(array_unique(array_map('intval', $validated['ids'] ?? [])));
        $newStatus = (string) $request->validated('status');
        $reason = $request->validated('reason');

        $updated = 0;
        $productsQuery = Product::query();
        if ($selectAll) {
            $fq = trim((string) data_get($validated, 'filters.q', ''));
            $fStatus = trim((string) data_get($validated, 'filters.status', ''));
            if ($fStatus !== '') {
                $productsQuery->where('status', $fStatus);
            }
            if ($fq !== '') {
                $productsQuery->where(function ($w) use ($fq): void {
                    $w->where('title', 'like', '%'.$fq.'%')
                        ->orWhere('id', $fq);
                });
            }
        } else {
            $productsQuery->whereIn('id', $ids);
        }

        $products = $productsQuery->get();
        foreach ($products as $product) {
            $before = ['status' => $product->status, 'published_at' => $product->published_at?->toIso8601String()];
            $product->status = $newStatus;
            if ($newStatus === 'published' && $product->published_at === null) {
                $product->published_at = now();
            }
            if ($newStatus !== 'published') {
                $product->published_at = null;
            }
            $product->save();
            $updated++;

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.product.bulk_status_updated',
                targetType: 'product',
                targetId: $product->id,
                beforeJson: $before,
                afterJson: ['status' => $product->status, 'published_at' => $product->published_at?->toIso8601String()],
                reasonCode: $reason,
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return redirect()->route('admin.products.index')->with('success', "Bulk moderation applied to {$updated} product(s).");
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\BulkProductModerationRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Database\Eloquent\Builder;
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
            $fType = trim((string) data_get($validated, 'filters.type', ''));
            if ($fStatus !== '') {
                $productsQuery->where('status', $fStatus);
            }
            if ($fType !== '') {
                $this->applyProductTypeFilter($productsQuery, $fType);
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

    private function applyProductTypeFilter(Builder $query, string $type): void
    {
        $normalized = strtolower(trim($type));

        if ($normalized === 'instant_delivery') {
            $query->where(static function (Builder $builder): void {
                $builder->where('product_type', 'instant_delivery')
                    ->orWhere(static function (Builder $digital): void {
                        $digital->where('product_type', 'digital')
                            ->where(static function (Builder $flags): void {
                                $flags->where('attributes_json', 'like', '%"is_instant_delivery":true%')
                                    ->orWhere('attributes_json', 'like', '%"is_instant_delivery":"1"%')
                                    ->orWhere('attributes_json', 'like', '%"is_instant_delivery":1%')
                                    ->orWhere('attributes_json', 'like', '%"delivery_mode":"instant"%')
                                    ->orWhere('attributes_json', 'like', '%"delivery_type":"instant_delivery"%')
                                    ->orWhere('attributes_json', 'like', '%"delivery_type":"instant"%');
                            });
                    });
            });

            return;
        }

        if ($normalized === 'digital') {
            $query->where('product_type', 'digital')
                ->where(static function (Builder $builder): void {
                    $builder->whereNull('attributes_json')
                        ->orWhere(static function (Builder $flags): void {
                            $flags->where('attributes_json', 'not like', '%"is_instant_delivery":true%')
                                ->where('attributes_json', 'not like', '%"is_instant_delivery":"1"%')
                                ->where('attributes_json', 'not like', '%"is_instant_delivery":1%')
                                ->where('attributes_json', 'not like', '%"delivery_mode":"instant"%')
                                ->where('attributes_json', 'not like', '%"delivery_type":"instant_delivery"%')
                                ->where('attributes_json', 'not like', '%"delivery_type":"instant"%');
                        });
                });

            return;
        }

        $query->where('product_type', $normalized);
    }
}

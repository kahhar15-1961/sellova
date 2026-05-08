<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Models\ShippingMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ShippingMethodsController extends AdminPageController
{
    public function index(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Admin/ShippingMethods/Index', [
            'header' => $this->pageHeader(
                'Shipping methods',
                'Define seller-selectable delivery zones, suggested prices, and default processing times.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Shipping methods'],
                ],
            ),
            'rows' => ShippingMethod::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(static fn (ShippingMethod $method): array => [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->name,
                    'suggested_fee' => number_format((float) $method->suggested_fee, 2, '.', ''),
                    'processing_time_label' => $method->processing_time_label,
                    'is_active' => (bool) $method->is_active,
                    'sort_order' => (int) $method->sort_order,
                    'updated_at' => $method->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'store_url' => route('admin.shipping-methods.store'),
            'update_url_template' => route('admin.shipping-methods.update', ['shippingMethod' => '__ID__']),
            'toggle_url_template' => route('admin.shipping-methods.toggle', ['shippingMethod' => '__ID__']),
            'processing_options' => ['Instant', 'Same day', '1-2 Business Days', '3-5 Business Days', '5-7 Business Days'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validatePayload($request);
        ShippingMethod::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => $this->uniqueCode($data['code'] ?? $data['name']),
            'name' => trim((string) $data['name']),
            'suggested_fee' => (float) $data['suggested_fee'],
            'processing_time_label' => (string) $data['processing_time_label'],
            'is_active' => filter_var((string) ($data['is_active'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()->route('admin.shipping-methods.index')->with('success', 'Shipping method created.');
    }

    public function update(Request $request, ShippingMethod $shippingMethod): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validatePayload($request, partial: true);
        $shippingMethod->fill([
            'name' => trim((string) ($data['name'] ?? $shippingMethod->name)),
            'suggested_fee' => (float) ($data['suggested_fee'] ?? $shippingMethod->suggested_fee),
            'processing_time_label' => (string) ($data['processing_time_label'] ?? $shippingMethod->processing_time_label),
            'is_active' => filter_var((string) ($data['is_active'] ?? $shippingMethod->is_active), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            'sort_order' => (int) ($data['sort_order'] ?? $shippingMethod->sort_order),
        ]);
        if (array_key_exists('code', $data) && trim((string) $data['code']) !== $shippingMethod->code) {
            $shippingMethod->code = $this->uniqueCode((string) $data['code'], (int) $shippingMethod->id);
        }
        $shippingMethod->save();

        return redirect()->route('admin.shipping-methods.index')->with('success', 'Shipping method updated.');
    }

    public function toggle(Request $request, ShippingMethod $shippingMethod): RedirectResponse
    {
        $this->authorizeManage($request);
        $shippingMethod->forceFill([
            'is_active' => filter_var((string) $request->input('is_active', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ])->save();

        return redirect()->route('admin.shipping-methods.index')->with('success', 'Shipping method status updated.');
    }

    private function authorizeManage(Request $request): void
    {
        $actor = $request->user();
        if ($actor === null || (! $actor->isPlatformStaff() && ! $actor->hasPermissionCode(AdminPermission::SETTINGS_MANAGE))) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name' => [$required, 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:96'],
            'suggested_fee' => [$required, 'numeric', 'min:0'],
            'processing_time_label' => [$required, 'string', 'max:80'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }

    private function uniqueCode(string $value, ?int $exceptId = null): string
    {
        $base = Str::slug($value, '_') ?: 'shipping_method';
        $code = $base;
        $i = 2;
        while (ShippingMethod::query()
            ->where('code', $code)
            ->when($exceptId !== null, static fn ($q) => $q->whereKeyNot($exceptId))
            ->exists()) {
            $code = $base.'_'.$i;
            $i += 1;
        }

        return $code;
    }
}

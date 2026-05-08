<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\PaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentGatewaysController extends AdminPageController
{
    public function __construct(private readonly PaymentGatewayService $gateways)
    {
    }

    public function index(Request $request): Response
    {
        $gateways = $this->gateways->list();
        $editing = null;
        $editId = (int) $request->query('edit', 0);
        if ($editId > 0) {
            $editing = PaymentGateway::query()->find($editId);
        }

        return Inertia::render('Admin/PaymentGateways/Index', [
            'header' => $this->pageHeader(
                'Payment Gateways',
                'Configure gateway providers, credentials, and checkout behavior without editing code.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Payment Gateways'],
                ],
            ),
            'gateways' => array_map(static fn (PaymentGateway $gateway): array => $gateway->publicPayload(), $gateways),
            'editing_gateway' => $editing ? $editing->publicPayload() + [
                'credentials' => $editing->credentials,
            ] : null,
            'store_url' => route('admin.payment-gateways.store'),
            'update_url_template' => route('admin.payment-gateways.update', ['paymentGateway' => '__ID__']),
            'toggle_url_template' => route('admin.payment-gateways.toggle', ['paymentGateway' => '__ID__']),
            'test_url_template' => route('admin.payment-gateways.test', ['paymentGateway' => '__ID__']),
            'method_options' => [
                ['value' => 'card', 'label' => 'Card'],
                ['value' => 'bkash', 'label' => 'bKash'],
                ['value' => 'nagad', 'label' => 'Nagad'],
                ['value' => 'bank', 'label' => 'Bank'],
            ],
            'driver_options' => [
                ['value' => 'manual', 'label' => 'Manual capture'],
                ['value' => 'redirect', 'label' => 'Redirect checkout'],
                ['value' => 'api', 'label' => 'API session'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateGateway($request);
        $this->gateways->save($data);

        return redirect()->route('admin.payment-gateways.index')->with('success', 'Gateway saved.');
    }

    public function update(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $data = $this->validateGateway($request, $paymentGateway);
        $this->gateways->save($data, $paymentGateway);

        return redirect()->route('admin.payment-gateways.index')->with('success', 'Gateway updated.');
    }

    public function toggle(PaymentGateway $paymentGateway): RedirectResponse
    {
        $this->gateways->toggle($paymentGateway);

        return redirect()->route('admin.payment-gateways.index')->with('success', 'Gateway status updated.');
    }

    public function test(PaymentGateway $paymentGateway): RedirectResponse
    {
        $result = $this->gateways->test($paymentGateway);

        return redirect()
            ->route('admin.payment-gateways.index')
            ->with('gateway_test_result', $result)
            ->with('success', $result['summary']);
    }

    private function validateGateway(Request $request, ?PaymentGateway $gateway = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('payment_gateways', 'code')->ignore($gateway?->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'method' => ['required', 'string', Rule::in(['card', 'bkash', 'nagad', 'bank'])],
            'driver' => ['required', 'string', Rule::in(['manual', 'redirect', 'api'])],
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'supported_methods' => ['required', 'array', 'min:1'],
            'supported_methods.*' => ['string', Rule::in(['card', 'bkash', 'nagad', 'bank'])],
            'checkout_url' => ['nullable', 'string', 'max:512'],
            'callback_url' => ['nullable', 'string', 'max:512'],
            'webhook_url' => ['nullable', 'string', 'max:512'],
            'public_key' => ['nullable', 'string', 'max:256'],
            'merchant_id' => ['nullable', 'string', 'max:256'],
            'description' => ['nullable', 'string'],
            'credentials' => ['nullable'],
            'extra_json' => ['nullable'],
            'wallet_manual_top_up_enabled' => ['nullable', 'boolean'],
            'wallet_manual_top_up_label' => ['nullable', 'string', 'max:120'],
        ]);

        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['supported_methods'] = array_values(array_unique(array_map(
            static fn (string $method): string => strtolower(trim($method)),
            $data['supported_methods'] ?? [$data['method']],
        )));

        $data['credentials'] = $this->normalizeJsonValue($request->input('credentials', []));
        $data['extra_json'] = $this->normalizeJsonValue($request->input('extra_json', []));
        $data['extra_json']['wallet_manual_top_up_enabled'] = (bool) ($request->boolean('wallet_manual_top_up_enabled'));
        if ($request->filled('wallet_manual_top_up_label')) {
            $data['extra_json']['wallet_manual_top_up_label'] = trim((string) $request->input('wallet_manual_top_up_label'));
        }

        return $data;
    }

    private function normalizeJsonValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}

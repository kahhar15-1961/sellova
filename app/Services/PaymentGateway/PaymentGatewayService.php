<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Models\PaymentGateway;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

final class PaymentGatewayService
{
    /**
     * @return list<PaymentGateway>
     */
    public function list(): array
    {
        $this->bootstrapDefaults();

        return PaymentGateway::query()
            ->orderByDesc('is_default')
            ->orderByDesc('is_enabled')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @return list<PaymentGateway>
     */
    public function enabled(): array
    {
        $this->bootstrapDefaults();

        return PaymentGateway::query()
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @return list<PaymentGateway>
     */
    public function forMethod(string $method): array
    {
        $method = strtolower(trim($method));

        return PaymentGateway::query()
            ->where('is_enabled', true)
            ->get()
            ->filter(static fn (PaymentGateway $gateway): bool => $gateway->supportsMethod($method))
            ->sortByDesc('is_default')
            ->sortByDesc('priority')
            ->values()
            ->all();
    }

    public function resolveForMethod(string $method): ?PaymentGateway
    {
        return $this->forMethod($method)[0] ?? null;
    }

    public function save(array $payload, ?PaymentGateway $gateway = null): PaymentGateway
    {
        $gateway ??= new PaymentGateway();
        $gateway->code = strtolower(trim((string) ($payload['code'] ?? $gateway->code ?? '')));
        $gateway->name = trim((string) ($payload['name'] ?? $gateway->name ?? ''));
        $gateway->method = strtolower(trim((string) ($payload['method'] ?? $gateway->method ?? 'card')));
        $gateway->driver = strtolower(trim((string) ($payload['driver'] ?? $gateway->driver ?? 'manual')));
        $gateway->is_enabled = (bool) ($payload['is_enabled'] ?? false);
        $gateway->is_default = (bool) ($payload['is_default'] ?? false);
        $gateway->priority = (int) ($payload['priority'] ?? 0);
        $gateway->supported_methods = array_values(array_unique(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            Arr::wrap($payload['supported_methods'] ?? [$gateway->method]),
        )));
        $gateway->checkout_url = $this->nullableString($payload['checkout_url'] ?? null);
        $gateway->callback_url = $this->nullableString($payload['callback_url'] ?? null);
        $gateway->webhook_url = $this->nullableString($payload['webhook_url'] ?? null);
        $gateway->public_key = $this->nullableString($payload['public_key'] ?? null);
        $gateway->merchant_id = $this->nullableString($payload['merchant_id'] ?? null);
        $gateway->description = $this->nullableString($payload['description'] ?? null);
        $gateway->extra_json = $this->normalizeJson($payload['extra_json'] ?? []);
        $gateway->credentials = $this->normalizeJson($payload['credentials'] ?? []);

        if ($gateway->is_default) {
            DB::transaction(function () use ($gateway): void {
                PaymentGateway::query()
                    ->where('id', '!=', $gateway->id ?? 0)
                    ->update(['is_default' => false]);
                $gateway->save();
            });
            return $gateway->fresh();
        }

        $gateway->save();

        return $gateway->fresh();
    }

    public function toggle(PaymentGateway $gateway): PaymentGateway
    {
        $gateway->is_enabled = ! $gateway->is_enabled;
        $gateway->save();
        return $gateway->fresh();
    }

    public function test(PaymentGateway $gateway): array
    {
        $checks = [];
        $issues = [];

        $this->pushCheck($checks, 'Code', $gateway->code !== '', $gateway->code !== '' ? $gateway->code : 'Missing code', $issues);
        $this->pushCheck($checks, 'Name', $gateway->name !== '', $gateway->name !== '' ? $gateway->name : 'Missing name', $issues);
        $this->pushCheck($checks, 'Methods', $gateway->supported_methods !== [], $gateway->supported_methods !== [] ? implode(', ', $gateway->supported_methods) : 'No methods configured', $issues);
        $this->pushCheck($checks, 'Credentials', $gateway->credentialsPresent(), $gateway->credentialsPresent() ? 'Stored securely' : 'No credentials saved', $issues, false);

        $urlTargets = array_filter([
            'checkout' => $gateway->checkout_url,
            'callback' => $gateway->callback_url,
            'webhook' => $gateway->webhook_url,
        ]);

        foreach ($urlTargets as $label => $url) {
            $result = $this->validateUrl((string) $url);
            $checks[] = $result + ['label' => ucfirst($label) . ' URL'];
            if ($result['status'] !== 'pass') {
                $issues[] = $result['message'];
            }
        }

        $liveReachability = null;
        if ($gateway->driver !== 'manual' && is_string($gateway->checkout_url) && trim($gateway->checkout_url) !== '') {
            try {
                $response = Http::timeout(5)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => 'SellovaPaymentGatewayTester/1.0'])
                    ->head($gateway->checkout_url);
                $ok = $response->successful() || in_array($response->status(), [405, 406], true);
                $liveReachability = [
                    'label' => 'Endpoint reachability',
                    'status' => $ok ? 'pass' : 'warn',
                    'message' => $ok
                        ? 'Endpoint responded to the server.'
                        : 'Endpoint returned a non-success response.',
                ];
            } catch (\Throwable $e) {
                $liveReachability = [
                    'label' => 'Endpoint reachability',
                    'status' => 'warn',
                    'message' => 'Could not verify from this environment.',
                ];
            }
            $checks[] = $liveReachability;
            if (($liveReachability['status'] ?? 'warn') === 'warn') {
                $issues[] = $liveReachability['message'];
            }
        } elseif ($gateway->driver !== 'manual') {
            $checks[] = [
                'label' => 'Endpoint reachability',
                'status' => 'warn',
                'message' => 'Set checkout URL to enable live verification.',
            ];
            $issues[] = 'Set checkout URL to enable live verification.';
        }

        $hasFail = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'fail')) > 0;
        $hasWarn = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'warn')) > 0;
        $status = $hasFail ? 'fail' : ($hasWarn ? 'warn' : 'pass');

        return [
            'gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'gateway_name' => $gateway->name,
            'status' => $status,
            'summary' => match ($status) {
                'pass' => 'Gateway passed configuration checks.',
                'warn' => 'Gateway is usable, but one or more checks need attention.',
                default => 'Gateway has blocking configuration issues.',
            },
            'checks' => $checks,
            'issues' => array_values(array_unique($issues)),
            'tested_at' => now()->toIso8601String(),
        ];
    }

    private function bootstrapDefaults(): void
    {
        if (PaymentGateway::query()->exists()) {
            return;
        }

        $rows = [
            [
                'code' => 'sslcommerz',
                'name' => 'SSLCommerz',
                'method' => 'card',
                'driver' => 'manual',
                'supported_methods' => ['card', 'bkash', 'nagad', 'bank'],
                'is_enabled' => false,
                'is_default' => true,
                'priority' => 100,
                'description' => 'Configure hosted checkout, merchant credentials, and webhook URL.',
            ],
            [
                'code' => 'bkash',
                'name' => 'bKash',
                'method' => 'bkash',
                'driver' => 'manual',
                'supported_methods' => ['bkash'],
                'is_enabled' => false,
                'priority' => 90,
                'description' => 'Configure bKash credentials and callback handling.',
            ],
            [
                'code' => 'nagad',
                'name' => 'Nagad',
                'method' => 'nagad',
                'driver' => 'manual',
                'supported_methods' => ['nagad'],
                'is_enabled' => false,
                'priority' => 80,
                'description' => 'Configure Nagad merchant credentials and callback handling.',
            ],
            [
                'code' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'method' => 'bank',
                'driver' => 'manual',
                'supported_methods' => ['bank'],
                'is_enabled' => true,
                'priority' => 70,
                'description' => 'Configure bank deposit instructions and review flow.',
                'extra_json' => [
                    'wallet_manual_top_up_enabled' => true,
                    'wallet_manual_top_up_label' => 'Manual review',
                ],
            ],
        ];

        foreach ($rows as $row) {
            $this->save($row);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function normalizeJson(mixed $value): array
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

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $issues
     */
    private function pushCheck(array &$checks, string $label, bool $ok, string $message, array &$issues, bool $countIssue = true): void
    {
        $checks[] = [
            'label' => $label,
            'status' => $ok ? 'pass' : 'fail',
            'message' => $message,
        ];
        if (! $ok && $countIssue) {
            $issues[] = $message;
        }
    }

    /**
     * @return array{label:string,status:string,message:string}
     */
    private function validateUrl(string $url): array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return [
                'label' => 'URL',
                'status' => 'fail',
                'message' => 'Invalid URL: '.$url,
            ];
        }

        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        if (! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return [
                'label' => 'URL',
                'status' => 'fail',
                'message' => 'Unsupported URL scheme: '.$url,
            ];
        }

        return [
            'label' => 'URL',
            'status' => 'pass',
            'message' => 'Valid URL.',
        ];
    }
}

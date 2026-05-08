<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $method
 * @property string $driver
 * @property bool $is_enabled
 * @property bool $is_default
 * @property int $priority
 * @property string|null $checkout_url
 * @property string|null $callback_url
 * @property string|null $webhook_url
 * @property string|null $public_key
 * @property string|null $merchant_id
 * @property string|null $credentials_json
 * @property array $supported_methods
 * @property array $extra_json
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentGateway extends Model
{
    protected $table = 'payment_gateways';

    protected $fillable = [
        'code',
        'name',
        'method',
        'driver',
        'is_enabled',
        'is_default',
        'priority',
        'checkout_url',
        'callback_url',
        'webhook_url',
        'public_key',
        'merchant_id',
        'credentials_json',
        'supported_methods',
        'extra_json',
        'description',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'priority' => 'integer',
        'supported_methods' => 'array',
        'extra_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials_json',
    ];

    public function getCredentialsAttribute(): array
    {
        if ($this->credentials_json === null || trim((string) $this->credentials_json) === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString((string) $this->credentials_json), true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setCredentialsAttribute(array|string|null $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['credentials_json'] = null;
            return;
        }

        $payload = is_array($value) ? $value : json_decode((string) $value, true);
        if (! is_array($payload)) {
            $payload = ['raw' => (string) $value];
        }

        $this->attributes['credentials_json'] = Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function supportsMethod(string $method): bool
    {
        return in_array(strtolower(trim($method)), array_map('strtolower', $this->supported_methods ?? []), true);
    }

    public function publicPayload(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'method' => $this->method,
            'driver' => $this->driver,
            'driver_label' => $this->driverLabel(),
            'integration_state' => $this->integrationState(),
            'is_enabled' => $this->is_enabled,
            'is_default' => $this->is_default,
            'priority' => $this->priority,
            'checkout_url' => $this->checkout_url,
            'callback_url' => $this->callback_url,
            'webhook_url' => $this->webhook_url,
            'public_key' => $this->public_key,
            'merchant_id' => $this->merchant_id,
            'supported_methods' => $this->supported_methods ?? [],
            'description' => $this->description,
            'extra_json' => $this->extra_json ?? [],
            'wallet_manual_top_up_enabled' => $this->walletManualTopUpEnabled(),
            'wallet_manual_top_up_label' => $this->walletManualTopUpLabel(),
            'credentials_present' => $this->credentialsPresent(),
            'credential_keys' => $this->credentialKeys(),
        ];
    }

    public function credentialsPresent(): bool
    {
        return $this->credentials !== [];
    }

    /**
     * @return list<string>
     */
    public function credentialKeys(): array
    {
        return array_values(array_keys($this->credentials));
    }

    public function driverLabel(): string
    {
        return match ($this->driver) {
            'redirect' => 'Redirect checkout',
            'api' => 'API session',
            default => 'Manual capture',
        };
    }

    public function integrationState(): string
    {
        if (! $this->is_enabled) {
            return 'disabled';
        }

        return match ($this->driver) {
            'redirect', 'api' => 'configured',
            default => 'manual',
        };
    }

    public function walletManualTopUpEnabled(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        $value = $this->extra_json['wallet_manual_top_up_enabled'] ?? null;
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function walletManualTopUpLabel(): string
    {
        $label = trim((string) ($this->extra_json['wallet_manual_top_up_label'] ?? ''));
        return $label !== '' ? $label : 'Manual review';
    }
}

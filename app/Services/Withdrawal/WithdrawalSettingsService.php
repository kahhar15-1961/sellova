<?php

namespace App\Services\Withdrawal;

use App\Models\WithdrawalSetting;

class WithdrawalSettingsService
{
    public const DEFAULT_MINIMUM = '500.0000';
    public const DEFAULT_CURRENCY = 'BDT';

    public function current(): WithdrawalSetting
    {
        $settings = WithdrawalSetting::query()->first();
        if ($settings !== null) {
            return $settings;
        }

        return WithdrawalSetting::query()->create([
            'minimum_withdrawal_amount' => self::DEFAULT_MINIMUM,
            'currency' => self::DEFAULT_CURRENCY,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(array $payload): WithdrawalSetting
    {
        $minimum = $this->normalizeMoney(
            (string) ($payload['minimum_withdrawal_amount'] ?? self::DEFAULT_MINIMUM),
        );

        $settings = $this->current();
        $settings->fill([
            'minimum_withdrawal_amount' => $minimum,
            'currency' => strtoupper((string) ($payload['currency'] ?? self::DEFAULT_CURRENCY)),
        ]);
        $settings->save();

        return $settings->fresh() ?? $settings;
    }

    public function minimumAmount(): string
    {
        return (string) $this->current()->minimum_withdrawal_amount;
    }

    private function normalizeMoney(string $value): string
    {
        $amount = (float) str_replace(',', '', trim($value));
        if ($amount < 0) {
            $amount = 0;
        }

        return number_format($amount, 4, '.', '');
    }
}

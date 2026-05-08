<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the baseline promotion catalog used by checkout and admin flows.
 */
final class PromotionSeeder
{
    public static function seedDefaults(): void
    {
        DB::transaction(static function (): void {
            self::upsertPromotion([
                'code' => 'WELCOME10',
                'title' => 'Welcome Bonus',
                'description' => '10% off on your first or next eligible order.',
                'badge' => '10% OFF',
                'currency' => 'USD',
                'discount_type' => 'percentage',
                'discount_value' => '0.1000',
                'min_spend' => '0.0000',
                'max_discount_amount' => '25.0000',
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'is_active' => true,
            ]);

            self::upsertPromotion([
                'code' => 'SAVE50',
                'title' => 'Instant Savings',
                'description' => 'Flat $50 discount on larger carts.',
                'badge' => '$50 OFF',
                'currency' => 'USD',
                'discount_type' => 'fixed',
                'discount_value' => '50.0000',
                'min_spend' => '500.0000',
                'max_discount_amount' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'is_active' => true,
            ]);

            self::upsertPromotion([
                'code' => 'FREESHIP',
                'title' => 'Free Shipping',
                'description' => 'Shipping waiver for eligible orders.',
                'badge' => 'FREE SHIP',
                'currency' => 'USD',
                'discount_type' => 'shipping',
                'discount_value' => '1.0000',
                'min_spend' => '0.0000',
                'max_discount_amount' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function upsertPromotion(array $attributes): void
    {
        Promotion::query()->updateOrCreate(
            ['code' => (string) $attributes['code']],
            [
                'uuid' => (string) Str::uuid(),
                'title' => (string) $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'badge' => $attributes['badge'] ?? null,
                'currency' => (string) $attributes['currency'],
                'discount_type' => (string) $attributes['discount_type'],
                'discount_value' => (string) $attributes['discount_value'],
                'min_spend' => (string) $attributes['min_spend'],
                'max_discount_amount' => $attributes['max_discount_amount'] ?? null,
                'starts_at' => $attributes['starts_at'] ?? null,
                'ends_at' => $attributes['ends_at'] ?? null,
                'usage_limit' => $attributes['usage_limit'] ?? null,
                'used_count' => (int) ($attributes['used_count'] ?? 0),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ],
        );
    }
}

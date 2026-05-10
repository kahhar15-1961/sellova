<?php

declare(strict_types=1);

namespace App\Services\Promotion;

use App\Domain\Exceptions\PromotionValidationFailedException;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PromotionService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listPromotions(?float $subtotal = null, string $currency = 'USD', string $shippingMethod = 'standard'): array
    {
        $currency = $this->normalizeCurrency($currency);
        $shippingMethod = $this->normalizeShippingMethod($shippingMethod);

        return Promotion::query()
            ->where('campaign_type', 'coupon')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Promotion $promo): array => $this->shapePromo(
                promo: $promo,
                subtotal: $subtotal,
                shippingFee: 0.0,
                currency: $currency,
                shippingMethod: $shippingMethod,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function validatePromoCode(string $rawCode, float $subtotal, float $shippingFee, string $currency = 'USD', string $shippingMethod = 'standard'): array
    {
        $code = $this->normalizeCode($rawCode);
        $currency = $this->normalizeCurrency($currency);
        $shippingMethod = $this->normalizeShippingMethod($shippingMethod);
        $promo = $this->activePromotionByCode($code);
        if ($promo === null) {
            throw new PromotionValidationFailedException($code, 'promo_code_not_found', [], 'Promo code not found.');
        }

        return $this->validatePromotionRecord($promo, $subtotal, $shippingFee, $currency, $shippingMethod);
    }

    public function consumePromoCode(string $rawCode, float $subtotal, float $shippingFee, string $currency = 'USD', string $shippingMethod = 'standard'): array
    {
        $code = $this->normalizeCode($rawCode);
        return DB::transaction(function () use ($code, $subtotal, $shippingFee, $currency, $shippingMethod): array {
            $promo = Promotion::query()
                ->where('code', $code)
                ->where('campaign_type', 'coupon')
                ->lockForUpdate()
                ->first();
            if ($promo === null || ! $this->isPromotionActive($promo)) {
                throw new PromotionValidationFailedException($code, 'promo_code_not_found', [], 'Promo code not found.');
            }

            $result = $this->validatePromotionRecord(
                $promo,
                $subtotal,
                $shippingFee,
                $currency,
                $shippingMethod,
            );

            if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
                throw new PromotionValidationFailedException($code, 'promo_code_usage_limit_reached', [
                    'usage_limit' => $promo->usage_limit,
                    'used_count' => $promo->used_count,
                ], 'Promo code usage limit has been reached.');
            }

            $promo->used_count = $promo->used_count + 1;
            $promo->save();

            return $result;
        });
    }

    public function shippingFeeForMethod(string $shippingMethod, bool $needsShipping): float
    {
        if (! $needsShipping) {
            return 0.0;
        }

        return $this->normalizeShippingMethod($shippingMethod) === 'express' ? 40.0 : 20.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function createPromotion(array $payload, ?int $createdByUserId = null): array
    {
        $promo = new Promotion();
        $promo->fill($this->normalizePromotionPayload($payload));
        $promo->uuid = (string) Str::uuid();
        $promo->created_by_user_id = $createdByUserId;
        $promo->used_count = max(0, (int) ($payload['used_count'] ?? 0));
        $promo->save();

        return $this->promotionToArray($promo);
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePromotion(Promotion $promotion, array $payload): array
    {
        $promotion->fill($this->normalizePromotionPayload($payload, partial: true));
        if (array_key_exists('used_count', $payload)) {
            $promotion->used_count = max(0, (int) $payload['used_count']);
        }
        $promotion->save();

        return $this->promotionToArray($promotion);
    }

    /**
     * @return array<string, mixed>
     */
    public function togglePromotion(Promotion $promotion, bool $active): array
    {
        $promotion->is_active = $active;
        $promotion->save();

        return $this->promotionToArray($promotion);
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePromotion(Promotion $promotion): array
    {
        $promotion->delete();

        return [
            'promotion_id' => $promotion->id,
            'deleted' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdminPromotions(): array
    {
        return Promotion::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Promotion $promotion): array => $this->promotionToArray($promotion))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bestCatalogCampaignForProduct(Product $product): ?array
    {
        $campaign = Promotion::query()
            ->where('campaign_type', 'catalog')
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Promotion $promotion): bool => $this->promotionTargetsProduct($promotion, $product));

        return $campaign instanceof Promotion ? $this->catalogCampaignPayload($campaign, $product) : null;
    }

    /**
     * @param  Promotion|array<string, mixed>  $promo
     * @return array<string, mixed>
     */
    private function shapePromo(
        Promotion|array $promo,
        ?float $subtotal,
        float $shippingFee,
        string $currency,
        string $shippingMethod,
        bool $eligible = false,
        ?float $discountAmount = null,
    ): array {
        $promoArr = $promo instanceof Promotion ? $this->promotionToArray($promo) : $promo;
        $minSpend = (float) ($promoArr['min_spend'] ?? 0);
        $computedDiscount = $discountAmount ?? (
            $subtotal === null ? null : $this->calculateDiscount($promoArr, $subtotal, $shippingFee, $shippingMethod)
        );
        $estimatedTotal = $subtotal === null || $computedDiscount === null
            ? null
            : max(0.0, $subtotal + $shippingFee - $computedDiscount);

        return [
            'id' => $promoArr['id'] ?? null,
            'code' => $promoArr['code'],
            'title' => $promoArr['title'],
            'description' => $promoArr['description'],
            'badge' => $promoArr['badge'],
            'currency' => $promoArr['currency'],
            'min_spend' => $this->money($minSpend),
            'min_spend_label' => $this->currencyLabel($minSpend, (string) $promoArr['currency']),
            'eligible' => $eligible || ($subtotal !== null && $subtotal >= $minSpend),
            'campaign_type' => $promoArr['campaign_type'] ?? 'coupon',
            'scope_type' => $promoArr['scope_type'] ?? 'all',
            'discount_type' => $promoArr['discount_type'],
            'discount_value' => (string) $promoArr['discount_value'],
            'estimated_discount_amount' => $computedDiscount === null ? null : $this->money($computedDiscount),
            'estimated_total_amount' => $estimatedTotal === null ? null : $this->money($estimatedTotal),
            'shipping_method' => $shippingMethod,
            'shipping_fee' => $this->money($shippingFee),
            'is_active' => $promoArr['is_active'],
            'usage_limit' => $promoArr['usage_limit'],
            'priority' => $promoArr['priority'] ?? 100,
            'marketing_channel' => $promoArr['marketing_channel'] ?? null,
            'used_count' => $promoArr['used_count'],
            'starts_at' => $promoArr['starts_at'],
            'ends_at' => $promoArr['ends_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $promo
     */
    private function calculateDiscount(array $promo, float $subtotal, float $shippingFee, string $shippingMethod): float
    {
        return match ((string) $promo['discount_type']) {
            'percentage' => round($subtotal * ((float) $promo['discount_value']), 4),
            'fixed' => (float) $promo['discount_value'],
            'shipping' => $shippingMethod === 'express' || $shippingMethod === 'standard' ? $shippingFee : 0.0,
            default => 0.0,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePromotionRecord(Promotion $promo, float $subtotal, float $shippingFee, string $currency, string $shippingMethod): array
    {
        if (! $this->isPromotionActive($promo)) {
            throw new PromotionValidationFailedException($promo->code, 'promo_code_inactive', [], 'Promo code is not active.');
        }

        if ($promo->currency !== $currency) {
            throw new PromotionValidationFailedException($promo->code, 'promo_code_currency_mismatch', [
                'expected_currency' => $promo->currency,
                'received_currency' => $currency,
            ], 'Promo code is not available for this currency.');
        }

        $minSpend = (float) $promo->min_spend;
        if ($subtotal < $minSpend) {
            throw new PromotionValidationFailedException($promo->code, 'promo_code_minimum_spend_not_met', [
                'minimum_spend' => $this->money($minSpend),
                'subtotal' => $this->money($subtotal),
            ], 'Promo code minimum spend has not been met.');
        }

        if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
            throw new PromotionValidationFailedException($promo->code, 'promo_code_usage_limit_reached', [
                'usage_limit' => $promo->usage_limit,
                'used_count' => $promo->used_count,
            ], 'Promo code usage limit has been reached.');
        }

        $discount = $this->calculateDiscount($this->promotionToArray($promo), $subtotal, $shippingFee, $shippingMethod);
        $discount = $this->applyMaxDiscountCap($promo, $discount);
        if ($discount <= 0) {
            throw new PromotionValidationFailedException($promo->code, 'promo_code_not_applicable', [
                'subtotal' => $this->money($subtotal),
                'shipping_fee' => $this->money($shippingFee),
            ], 'Promo code is not applicable to this order.');
        }

        $discount = min($discount, $subtotal + $shippingFee);

        return $this->shapePromo(
            promo: $promo,
            subtotal: $subtotal,
            shippingFee: $shippingFee,
            currency: $currency,
            shippingMethod: $shippingMethod,
            eligible: true,
            discountAmount: $discount,
        );
    }

    private function isPromotionActive(Promotion $promo): bool
    {
        if (! $promo->is_active) {
            return false;
        }

        $now = now();
        if ($promo->starts_at !== null && $promo->starts_at->greaterThan($now)) {
            return false;
        }
        if ($promo->ends_at !== null && $promo->ends_at->lessThan($now)) {
            return false;
        }
        if (! $this->isInsideDailyWindow($promo, $now->format('H:i'))) {
            return false;
        }

        return true;
    }

    private function activePromotionByCode(string $code): ?Promotion
    {
        $promo = Promotion::query()->where('code', $code)->where('campaign_type', 'coupon')->first();
        if ($promo === null || ! $this->isPromotionActive($promo)) {
            return null;
        }

        return $promo;
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionToArray(Promotion $promo): array
    {
        return [
            'id' => $promo->id,
            'uuid' => $promo->uuid,
            'code' => $promo->code,
            'title' => $promo->title,
            'description' => $promo->description,
            'badge' => $promo->badge,
            'campaign_type' => $promo->campaign_type ?? 'coupon',
            'scope_type' => $promo->scope_type ?? 'all',
            'target_product_ids' => $promo->target_product_ids ?? [],
            'target_seller_profile_ids' => $promo->target_seller_profile_ids ?? [],
            'target_category_ids' => $promo->target_category_ids ?? [],
            'target_product_types' => $promo->target_product_types ?? [],
            'currency' => $promo->currency,
            'discount_type' => $promo->discount_type,
            'discount_value' => (string) $promo->discount_value,
            'min_spend' => (string) $promo->min_spend,
            'max_discount_amount' => $promo->max_discount_amount !== null ? (string) $promo->max_discount_amount : null,
            'starts_at' => $promo->starts_at?->toIso8601String(),
            'ends_at' => $promo->ends_at?->toIso8601String(),
            'daily_start_time' => $promo->daily_start_time,
            'daily_end_time' => $promo->daily_end_time,
            'usage_limit' => $promo->usage_limit,
            'priority' => (int) ($promo->priority ?? 100),
            'marketing_channel' => $promo->marketing_channel,
            'created_by_user_id' => $promo->created_by_user_id,
            'used_count' => $promo->used_count,
            'is_active' => $promo->is_active,
            'created_at' => $promo->created_at?->toIso8601String(),
            'updated_at' => $promo->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePromotionPayload(array $payload, bool $partial = false): array
    {
        $out = [];
        if (! $partial || array_key_exists('code', $payload)) {
            $out['code'] = strtoupper(trim((string) ($payload['code'] ?? '')));
        }
        if (! $partial || array_key_exists('title', $payload)) {
            $out['title'] = trim((string) ($payload['title'] ?? ''));
        }
        if (! $partial || array_key_exists('description', $payload)) {
            $out['description'] = isset($payload['description']) ? trim((string) $payload['description']) : null;
        }
        if (! $partial || array_key_exists('badge', $payload)) {
            $out['badge'] = isset($payload['badge']) ? trim((string) $payload['badge']) : null;
        }
        if (! $partial || array_key_exists('campaign_type', $payload)) {
            $out['campaign_type'] = in_array((string) ($payload['campaign_type'] ?? 'coupon'), ['coupon', 'catalog'], true)
                ? (string) $payload['campaign_type']
                : 'coupon';
        }
        if (! $partial || array_key_exists('scope_type', $payload)) {
            $out['scope_type'] = in_array((string) ($payload['scope_type'] ?? 'all'), ['all', 'products', 'sellers', 'categories', 'product_types'], true)
                ? (string) $payload['scope_type']
                : 'all';
        }
        foreach ([
            'target_product_ids',
            'target_seller_profile_ids',
            'target_category_ids',
        ] as $field) {
            if (! $partial || array_key_exists($field, $payload)) {
                $out[$field] = $this->idList($payload[$field] ?? []);
            }
        }
        if (! $partial || array_key_exists('target_product_types', $payload)) {
            $out['target_product_types'] = $this->productTypeList($payload['target_product_types'] ?? []);
        }
        if (! $partial || array_key_exists('currency', $payload)) {
            $out['currency'] = $this->normalizeCurrency((string) ($payload['currency'] ?? 'USD'));
        }
        if (! $partial || array_key_exists('discount_type', $payload)) {
            $out['discount_type'] = $this->normalizeDiscountType((string) ($payload['discount_type'] ?? 'percentage'));
        }
        if (! $partial || array_key_exists('discount_value', $payload)) {
            $value = (float) ($payload['discount_value'] ?? 0);
            if (($out['discount_type'] ?? $payload['discount_type'] ?? null) === 'percentage' && $value > 1) {
                $value = $value / 100;
            }
            $out['discount_value'] = $this->money($value);
        }
        if (! $partial || array_key_exists('min_spend', $payload)) {
            $out['min_spend'] = $this->money((float) ($payload['min_spend'] ?? 0));
        }
        if (! $partial || array_key_exists('max_discount_amount', $payload)) {
            $out['max_discount_amount'] = isset($payload['max_discount_amount']) && $payload['max_discount_amount'] !== ''
                ? $this->money((float) $payload['max_discount_amount'])
                : null;
        }
        if (! $partial || array_key_exists('starts_at', $payload)) {
            $out['starts_at'] = $payload['starts_at'] ?? null;
        }
        if (! $partial || array_key_exists('ends_at', $payload)) {
            $out['ends_at'] = $payload['ends_at'] ?? null;
        }
        if (! $partial || array_key_exists('daily_start_time', $payload)) {
            $out['daily_start_time'] = $this->nullableTime($payload['daily_start_time'] ?? null);
        }
        if (! $partial || array_key_exists('daily_end_time', $payload)) {
            $out['daily_end_time'] = $this->nullableTime($payload['daily_end_time'] ?? null);
        }
        if (! $partial || array_key_exists('usage_limit', $payload)) {
            $out['usage_limit'] = isset($payload['usage_limit']) && $payload['usage_limit'] !== ''
                ? max(0, (int) $payload['usage_limit'])
                : null;
        }
        if (! $partial || array_key_exists('priority', $payload)) {
            $out['priority'] = max(0, (int) ($payload['priority'] ?? 100));
        }
        if (! $partial || array_key_exists('marketing_channel', $payload)) {
            $out['marketing_channel'] = isset($payload['marketing_channel']) ? mb_substr(trim((string) $payload['marketing_channel']), 0, 64) : null;
        }
        if (! $partial || array_key_exists('is_active', $payload)) {
            $out['is_active'] = (bool) ($payload['is_active'] ?? true);
        }

        return $out;
    }

    private function normalizeDiscountType(string $raw): string
    {
        $type = strtolower(trim($raw));
        return in_array($type, ['percentage', 'fixed', 'shipping'], true) ? $type : 'percentage';
    }

    private function applyMaxDiscountCap(Promotion $promo, float $discount): float
    {
        if ($promo->max_discount_amount === null) {
            return $discount;
        }

        return min($discount, (float) $promo->max_discount_amount);
    }

    private function normalizeCode(string $rawCode): string
    {
        return strtoupper(trim($rawCode));
    }

    private function normalizeCurrency(string $rawCurrency): string
    {
        $currency = strtoupper(trim($rawCurrency));
        return strlen($currency) === 3 ? $currency : 'USD';
    }

    private function normalizeShippingMethod(string $shippingMethod): string
    {
        return in_array(strtolower(trim($shippingMethod)), ['express', 'standard'], true)
            ? strtolower(trim($shippingMethod))
            : 'standard';
    }

    private function money(float $amount): string
    {
        return number_format(max(0.0, $amount), 4, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogCampaignPayload(Promotion $promotion, Product $product): array
    {
        $basePrice = (float) $product->base_price;
        $discount = $this->applyMaxDiscountCap($promotion, $this->calculateDiscount($this->promotionToArray($promotion), $basePrice, 0.0, 'standard'));
        $discount = min($discount, $basePrice);

        return [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'title' => $promotion->title,
            'badge' => $promotion->badge ?: $promotion->title,
            'discount_amount' => $this->money($discount),
            'discount_percentage' => $basePrice > 0 ? round(($discount / $basePrice) * 100, 2) : 0,
            'starts_at' => $promotion->starts_at?->toIso8601String(),
            'ends_at' => $promotion->ends_at?->toIso8601String(),
            'daily_start_time' => $promotion->daily_start_time,
            'daily_end_time' => $promotion->daily_end_time,
            'scope_type' => $promotion->scope_type,
            'priority' => (int) ($promotion->priority ?? 100),
        ];
    }

    private function promotionTargetsProduct(Promotion $promotion, Product $product): bool
    {
        return match ((string) ($promotion->scope_type ?? 'all')) {
            'products' => in_array((int) $product->id, $promotion->target_product_ids ?? [], true),
            'sellers' => in_array((int) $product->seller_profile_id, $promotion->target_seller_profile_ids ?? [], true),
            'categories' => in_array((int) $product->category_id, $promotion->target_category_ids ?? [], true),
            'product_types' => $this->promotionTargetsProductType($promotion, $product),
            default => true,
        };
    }

    /**
     * @return list<int>
     */
    private function idList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return list<string>
     */
    private function productTypeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $type): string => strtolower(trim((string) $type)),
            $value,
        ), static fn (string $type): bool => in_array($type, ['physical', 'digital', 'instant_delivery', 'service'], true))));
    }

    private function promotionTargetsProductType(Promotion $promotion, Product $product): bool
    {
        $targets = $promotion->target_product_types ?? [];
        $type = strtolower(trim((string) ($product->product_type ?? '')));
        $attributes = is_array($product->attributes_json) ? $product->attributes_json : [];
        $isInstantDelivery = in_array($type, ['instant_delivery', 'instant'], true)
            || filter_var($attributes['is_instant_delivery'] ?? false, FILTER_VALIDATE_BOOL)
            || strtolower(trim((string) ($attributes['delivery_mode'] ?? ''))) === 'instant'
            || in_array(strtolower(trim((string) ($attributes['delivery_type'] ?? ''))), ['instant_delivery', 'instant'], true);

        foreach ($targets as $target) {
            $normalizedTarget = strtolower(trim((string) $target));
            if ($normalizedTarget === 'instant_delivery' && $isInstantDelivery) {
                return true;
            }
            if ($normalizedTarget === 'digital' && $type === 'digital' && ! $isInstantDelivery) {
                return true;
            }
            if ($normalizedTarget === 'service' && in_array($type, ['service', 'manual_delivery'], true)) {
                return true;
            }
            if ($normalizedTarget === 'physical' && $type === 'physical') {
                return true;
            }
        }

        return false;
    }

    private function currencyLabel(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency));
        $formatted = number_format(max(0.0, $amount), 2, '.', '');
        return $currency === '' ? $formatted : $currency.' '.$formatted;
    }

    private function isInsideDailyWindow(Promotion $promo, string $now): bool
    {
        $start = $this->nullableTime($promo->daily_start_time);
        $end = $this->nullableTime($promo->daily_end_time);
        if ($start === null && $end === null) {
            return true;
        }
        if ($start !== null && $end === null) {
            return $now >= $start;
        }
        if ($start === null && $end !== null) {
            return $now <= $end;
        }
        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }

    private function nullableTime(mixed $value): ?string
    {
        $time = trim((string) ($value ?? ''));
        if ($time === '' || ! preg_match('/^\d{2}:\d{2}/', $time)) {
            return null;
        }

        return substr($time, 0, 5);
    }
}

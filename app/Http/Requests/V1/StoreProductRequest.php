<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Product\CreateProductCommand;
use App\Domain\Value\ProductDraft;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

final class StoreProductRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, User $actor): CreateProductCommand
    {
        $payload = self::validate($request);
        $sellerProfile = $actor->sellerProfile;
        if ($sellerProfile === null) {
            throw new \App\Domain\Exceptions\ProductValidationFailedException(null, 'seller_profile_not_found', [
                'user_id' => $actor->id,
            ]);
        }

        $storefrontId = (int) ($payload['storefront_id'] ?? ($sellerProfile->storefront?->id ?? 0));
        $productType = self::normalizeProductType((string) ($payload['product_type'] ?? 'physical'));

        return new CreateProductCommand(
            sellerProfileId: (int) $sellerProfile->id,
            draft: new ProductDraft(
                storefrontId: $storefrontId,
                categoryId: (int) $payload['category_id'],
                productType: $productType,
                title: (string) $payload['title'],
                description: isset($payload['description']) ? (string) $payload['description'] : null,
                basePrice: (string) $payload['base_price'],
                currency: strtoupper((string) $payload['currency']),
                stock: isset($payload['stock']) ? max(0, (int) $payload['stock']) : null,
                status: 'draft',
                discountPercentage: (string) ($payload['discount_percentage'] ?? '0'),
                discountLabel: isset($payload['discount_label']) ? (string) $payload['discount_label'] : null,
                imageUrl: isset($payload['image_url']) ? trim((string) $payload['image_url']) : null,
                imageUrls: self::normalizeImages($payload['images'] ?? null, $payload['image_url'] ?? null),
                attributes: self::normalizeAttributes($payload['attributes'] ?? [], $productType, $payload['is_instant_delivery'] ?? null),
            ),
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'title' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 255)],
                'currency' => [new NotBlank(), new Type('string'), new Length(exactly: 3)],
                'base_price' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 24)],
                'discount_percentage' => new Type('string'),
                'discount_label' => new Type('string'),
                'category_id' => [new NotBlank(), new Type('numeric'), new Positive()],
                'storefront_id' => new Type('numeric'),
                'product_type' => new Type('string'),
                'description' => new Type('string'),
                'stock' => new Type('numeric'),
                'image_url' => new Type('string'),
                'images' => new Type('array'),
                'attributes' => new Type('array'),
                'is_instant_delivery' => new Type('bool'),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeAttributes(
        mixed $raw,
        string $productType = 'physical',
        mixed $isInstantDelivery = null,
    ): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }
        $allowed = [
            'brand',
            'condition',
            'warranty_status',
            'warranty_period',
            'product_location',
            'tags',
            'digital_product_kind',
            'delivery_mode',
            'access_type',
            'subscription_duration',
            'account_region',
            'platform',
            'license_type',
            'delivery_note',
            'delivery_fulfillment_hours',
            'delivery_validity_hours',
            'buyer_confirmation_hours',
            'instant_delivery_expiration_hours',
            'digital_access_validity_hours',
            'is_instant_delivery',
        ];
        $attributes = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            if ($key === 'tags') {
                $tags = is_array($value) ? $value : explode(',', (string) $value);
                $attributes[$key] = array_values(array_filter(array_map(
                    static fn ($tag): string => trim((string) $tag),
                    $tags,
                )));
                continue;
            }
            if (in_array($key, [
                'delivery_fulfillment_hours',
                'delivery_validity_hours',
                'buyer_confirmation_hours',
                'instant_delivery_expiration_hours',
                'digital_access_validity_hours',
            ], true)) {
                $hours = (int) $value;
                if ($hours > 0) {
                    $attributes[$key] = $hours;
                }
                continue;
            }
            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                $attributes[$key] = $stringValue;
            }
        }

        $normalizedType = self::normalizeProductType($productType);
        if ($normalizedType === 'digital') {
            $flag = $isInstantDelivery;
            if ($flag === null && array_key_exists('is_instant_delivery', $raw)) {
                $flag = $raw['is_instant_delivery'];
            }
            $attributes['is_instant_delivery'] = filter_var($flag ?? false, FILTER_VALIDATE_BOOL);
        } else {
            unset($attributes['is_instant_delivery'], $attributes['instant_delivery_expiration_hours']);
        }

        return $attributes;
    }

    public static function normalizeProductType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'instant_delivery', 'instant-delivery', 'instant' => 'digital',
            'manual_delivery', 'manual-delivery', 'manual' => 'service',
            'digital', 'service' => strtolower(trim($type)),
            default => 'physical',
        };
    }

    /**
     * @return list<string>
     */
    public static function normalizeImages(mixed $raw, mixed $primary = null): array
    {
        $images = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $value = is_array($item)
                    ? (string) ($item['url'] ?? $item['path'] ?? '')
                    : (string) $item;
                $value = trim($value);
                if ($value !== '') {
                    $images[] = $value;
                }
            }
        }

        $primaryValue = trim((string) ($primary ?? ''));
        if ($primaryValue !== '') {
            array_unshift($images, $primaryValue);
        }

        return array_values(array_unique($images));
    }
}

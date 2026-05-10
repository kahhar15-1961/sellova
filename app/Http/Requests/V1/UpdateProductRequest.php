<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Product\UpdateProductCommand;
use App\Domain\Value\ProductDraft;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\Product;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class UpdateProductRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, User $actor, Product $product): UpdateProductCommand
    {
        $payload = self::validate($request);
        $sellerProfile = $actor->sellerProfile;
        if ($sellerProfile === null) {
            throw new \App\Domain\Exceptions\ProductValidationFailedException($product->id, 'seller_profile_not_found', [
                'user_id' => $actor->id,
            ]);
        }

        $imageUrls = array_key_exists('images', $payload) || array_key_exists('image_url', $payload)
            ? StoreProductRequest::normalizeImages($payload['images'] ?? null, $payload['image_url'] ?? null)
            : ($product->images_json ?? ($product->image_url ? [$product->image_url] : []));
        $imageUrl = array_key_exists('image_url', $payload)
            ? trim((string) $payload['image_url'])
            : (array_key_exists('images', $payload) ? ($imageUrls[0] ?? null) : ($product->image_url ?? null));
        $productType = array_key_exists('product_type', $payload)
            ? StoreProductRequest::normalizeProductType((string) $payload['product_type'])
            : StoreProductRequest::normalizeProductType((string) $product->product_type);
        $existingAttributes = is_array($product->attributes_json) ? $product->attributes_json : [];
        $isInstantDelivery = array_key_exists('is_instant_delivery', $payload)
            ? $payload['is_instant_delivery']
            : ($existingAttributes['is_instant_delivery'] ?? in_array(strtolower((string) $product->product_type), ['instant_delivery', 'instant'], true));

        return new UpdateProductCommand(
            productId: $product->id,
            sellerProfileId: (int) $sellerProfile->id,
            draft: new ProductDraft(
                storefrontId: (int) $product->storefront_id,
                categoryId: (int) ($payload['category_id'] ?? $product->category_id),
                productType: $productType,
                title: (string) ($payload['title'] ?? $product->title ?? ''),
                description: array_key_exists('description', $payload) ? (string) $payload['description'] : $product->description,
                basePrice: (string) ($payload['base_price'] ?? $product->base_price),
                currency: strtoupper((string) ($payload['currency'] ?? $product->currency ?? 'USD')),
                stock: array_key_exists('stock', $payload) ? max(0, (int) $payload['stock']) : null,
                status: (string) $product->status,
                discountPercentage: (string) ($payload['discount_percentage'] ?? $product->discount_percentage ?? '0'),
                discountLabel: array_key_exists('discount_label', $payload) ? (string) $payload['discount_label'] : $product->discount_label,
                imageUrl: $imageUrl,
                imageUrls: $imageUrls,
                attributes: array_key_exists('attributes', $payload)
                    ? StoreProductRequest::normalizeAttributes($payload['attributes'], $productType, $isInstantDelivery)
                    : StoreProductRequest::normalizeAttributes($existingAttributes, $productType, $isInstantDelivery),
            ),
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'title' => new Optional([new Type('string'), new Length(min: 1, max: 255)]),
                'currency' => new Optional([new Type('string'), new Length(exactly: 3)]),
                'base_price' => new Optional([new Type('string'), new Length(min: 1, max: 24)]),
                'discount_percentage' => new Optional([new Type('string')]),
                'discount_label' => new Optional([new Type('string')]),
                'category_id' => new Optional([new Type('numeric'), new Positive()]),
                'storefront_id' => new Optional([new Type('numeric'), new Positive()]),
                'product_type' => new Optional([new Type('string')]),
                'description' => new Optional([new Type('string')]),
                'stock' => new Optional([new Type('numeric')]),
                'image_url' => new Optional([new Type('string')]),
                'images' => new Optional([new Type('array')]),
                'attributes' => new Optional([new Type('array')]),
                'is_instant_delivery' => new Optional([new Type('bool')]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

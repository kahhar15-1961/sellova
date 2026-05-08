<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Order\CreateOrderCommand;
use App\Domain\Value\CartLineItem;
use App\Domain\Value\CartSnapshot;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

final class CreateOrderRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, User $actor): CreateOrderCommand
    {
        $payload = self::validate($request);
        if (isset($payload['cart_id'])) {
            $shippingMethodProvided = array_key_exists('shipping_method', $payload);
            $cart = \App\Models\Cart::query()
                ->whereKey((int) $payload['cart_id'])
                ->where('buyer_user_id', $actor->id)
                ->with(['cartItems'])
                ->first();
            if ($cart === null) {
                throw new \App\Domain\Exceptions\OrderValidationFailedException((int) ($payload['cart_id'] ?? 0), 'cart_not_found', [
                    'cart_id' => $payload['cart_id'],
                ]);
            }

            $lines = [];
            foreach ($cart->cartItems as $item) {
                $lines[] = new CartLineItem(
                    productId: (int) $item->product_id,
                    productVariantId: $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                    sellerProfileId: (int) $item->seller_profile_id,
                    quantity: (int) $item->quantity,
                    unitPrice: (string) $item->unit_price_snapshot,
                    currency: (string) $item->currency_snapshot,
                );
            }

            return new CreateOrderCommand(
                buyerUserId: (int) $actor->id,
                cartSnapshot: new CartSnapshot($lines),
                idempotencyKey: self::idempotencyKey($payload, $actor),
                shippingMethod: (string) ($payload['shipping_method'] ?? 'standard'),
                shippingMethodProvided: $shippingMethodProvided,
                shippingAddressId: isset($payload['shipping_address_id']) ? (string) $payload['shipping_address_id'] : null,
                shippingRecipientName: isset($payload['shipping_recipient_name']) ? (string) $payload['shipping_recipient_name'] : null,
                shippingAddressLine: isset($payload['shipping_address_line']) ? (string) $payload['shipping_address_line'] : null,
                shippingPhone: isset($payload['shipping_phone']) ? (string) $payload['shipping_phone'] : null,
                promoCode: isset($payload['promo_code']) && trim((string) $payload['promo_code']) !== ''
                    ? strtoupper(trim((string) $payload['promo_code']))
                    : null,
            );
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $payload['lines'] ?? [];
        $shippingMethodProvided = array_key_exists('shipping_method', $payload);
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = new CartLineItem(
                productId: (int) $row['product_id'],
                productVariantId: isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null,
                sellerProfileId: isset($row['seller_profile_id']) ? (int) $row['seller_profile_id'] : 0,
                quantity: (int) $row['quantity'],
                unitPrice: (string) $row['unit_price'],
                currency: (string) $row['currency'],
            );
        }

        return new CreateOrderCommand(
            buyerUserId: (int) $actor->id,
            cartSnapshot: new CartSnapshot($lines),
            idempotencyKey: self::idempotencyKey($payload, $actor),
            shippingMethod: (string) ($payload['shipping_method'] ?? 'standard'),
            shippingMethodProvided: $shippingMethodProvided,
            shippingAddressId: isset($payload['shipping_address_id']) ? (string) $payload['shipping_address_id'] : null,
            shippingRecipientName: isset($payload['shipping_recipient_name']) ? (string) $payload['shipping_recipient_name'] : null,
            shippingAddressLine: isset($payload['shipping_address_line']) ? (string) $payload['shipping_address_line'] : null,
            shippingPhone: isset($payload['shipping_phone']) ? (string) $payload['shipping_phone'] : null,
            promoCode: isset($payload['promo_code']) && trim((string) $payload['promo_code']) !== ''
                ? strtoupper(trim((string) $payload['promo_code']))
                : null,
        );
    }

    protected static function constraint(): Constraint
    {
        $line = new Collection([
            'fields' => [
                'product_id' => [new NotBlank(), new Type('numeric'), new Positive()],
                'product_variant_id' => new Optional([new Type('numeric'), new Positive()]),
                'seller_profile_id' => new Optional([new Type('numeric'), new Positive()]),
                'quantity' => [new NotBlank(), new Type('numeric'), new Positive()],
                'unit_price' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 24)],
                'currency' => [new NotBlank(), new Type('string'), new Length(exactly: 3)],
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);

        return new Collection([
            'fields' => [
                'lines' => new Optional([new Type('array'), new Count(min: 1), new All($line)]),
                'cart_id' => new Optional([new Type('numeric')]),
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
                'shipping_method' => new Optional([new Type('string'), new Choice(['standard', 'express'])]),
                'shipping_address_id' => new Optional([new Type('string'), new Length(max: 191)]),
                'shipping_recipient_name' => new Optional([new Type('string'), new Length(max: 191)]),
                'shipping_address_line' => new Optional([new Type('string'), new Length(max: 5000)]),
                'shipping_phone' => new Optional([new Type('string'), new Length(max: 32)]),
                'promo_code' => new Optional([new Type('string'), new Length(max: 64)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function idempotencyKey(array $payload, User $actor): string
    {
        $key = (string) ($payload['correlation_id'] ?? '');
        if ($key !== '') {
            return $key;
        }

        return 'order-create-'.$actor->id.'-'.substr(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 0, 32);
    }
}

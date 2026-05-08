<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Type;

final class ValidatePromoCodeRequest extends AbstractValidatedRequest
{
    /**
     * @return array{code: string, subtotal: float, shipping_fee: float, currency: string, shipping_method: string}
     */
    public static function payload(Request $request): array
    {
        $payload = self::validate($request);

        return [
            'code' => strtoupper(trim((string) ($payload['code'] ?? ''))),
            'subtotal' => (float) ($payload['subtotal'] ?? 0),
            'shipping_fee' => (float) ($payload['shipping_fee'] ?? 0),
            'currency' => strtoupper(trim((string) ($payload['currency'] ?? 'USD'))),
            'shipping_method' => strtolower(trim((string) ($payload['shipping_method'] ?? 'standard'))),
        ];
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'code' => [new NotBlank(), new Type('string'), new Length(min: 2, max: 64)],
                'subtotal' => [new Optional([new Type('numeric'), new PositiveOrZero()])],
                'shipping_fee' => [new Optional([new Type('numeric'), new PositiveOrZero()])],
                'currency' => [new Optional([new Type('string'), new Length(exactly: 3)])],
                'shipping_method' => [new Optional([new Type('string'), new Choice(['standard', 'express'])])],
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

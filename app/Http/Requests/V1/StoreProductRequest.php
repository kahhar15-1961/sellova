<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

final class StoreProductRequest extends AbstractValidatedRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(Request $request): array
    {
        return self::validate($request);
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'title' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 255)],
                'currency' => [new NotBlank(), new Type('string'), new Length(exactly: 3)],
                'base_price' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 24)],
                'seller_profile_id' => new Optional([new Type('numeric'), new Positive()]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

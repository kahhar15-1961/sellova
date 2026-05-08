<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Optional;

final class SubmitOrderManualPaymentRequest extends AbstractValidatedRequest
{
    public static function payload(Request $request): array
    {
        return self::validate($request);
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'provider' => [new NotBlank(), new Type('string'), new Choice(['card', 'bkash', 'nagad', 'bank'])],
                'provider_reference' => [new NotBlank(), new Type('string'), new Length(max: 191)],
                'correlation_id' => new Optional([
                    new Type('string'),
                    new Length(max: 191),
                ]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

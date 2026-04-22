<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class UpdateProfileRequest extends AbstractValidatedRequest
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
                'display_name' => new Optional([new Type('string'), new Length(max: 191)]),
                'email' => new Optional([new Type('string'), new Email()]),
                'phone' => new Optional([new Type('string'), new Length(max: 32)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

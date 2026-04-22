<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

final class LoginRequest extends AbstractValidatedRequest
{
    /**
     * @return array{email: string, password: string}
     */
    public static function credentials(Request $request): array
    {
        $payload = self::validate($request);

        return [
            'email' => (string) $payload['email'],
            'password' => (string) $payload['password'],
        ];
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'email' => [new NotBlank(), new Type('string'), new Email()],
                'password' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 1024)],
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

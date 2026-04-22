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
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class RegisterRequest extends AbstractValidatedRequest
{
    /**
     * @return array{email: string, password: string, role?: string}
     */
    public static function payload(Request $request): array
    {
        $p = self::validate($request);
        $out = [
            'email' => (string) $p['email'],
            'password' => (string) $p['password'],
        ];
        if (isset($p['role'])) {
            $out['role'] = (string) $p['role'];
        }

        return $out;
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'email' => [new NotBlank(), new Type('string'), new Email()],
                'password' => [new NotBlank(), new Type('string'), new Length(min: 8, max: 1024)],
                'role' => new Optional([new Type('string'), new Length(max: 32)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

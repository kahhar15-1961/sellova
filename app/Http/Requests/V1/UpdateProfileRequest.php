<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\UserSeller\UpdateUserProfileCommand;
use App\Domain\Value\UserProfilePatch;
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

    public static function toCommand(Request $request, int $userId): UpdateUserProfileCommand
    {
        $p = self::validate($request);
        $email = isset($p['email']) ? trim((string) $p['email']) : null;
        if ($email === '') {
            $email = null;
        }
        $phone = isset($p['phone']) ? trim((string) $p['phone']) : null;
        if ($phone === '') {
            $phone = null;
        }
        $passwordPlain = isset($p['password']) ? (string) $p['password'] : null;
        if ($passwordPlain === '') {
            $passwordPlain = null;
        }

        return new UpdateUserProfileCommand(
            $userId,
            new UserProfilePatch(
                displayName: null,
                email: $email,
                phone: $phone,
                passwordPlain: $passwordPlain,
            ),
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'email' => new Optional([new Type('string'), new Email()]),
                'phone' => new Optional([new Type('string'), new Length(max: 32)]),
                'password' => new Optional([new Type('string'), new Length(min: 8, max: 1024)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

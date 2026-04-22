<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

final class RefreshSessionRequest extends AbstractValidatedRequest
{
    public static function token(Request $request): string
    {
        $payload = self::validate($request);

        return (string) $payload['refresh_token'];
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'refresh_token' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 2048)],
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

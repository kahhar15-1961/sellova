<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class CancelOrderRequest extends AbstractValidatedRequest
{
    /**
     * @return array{reason: ?string, correlation_id: ?string}
     */
    public static function payload(Request $request): array
    {
        $payload = self::validate($request);

        return [
            'reason' => isset($payload['reason']) ? trim((string) $payload['reason']) : null,
            'correlation_id' => isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        ];
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'reason' => new Optional([new Type('string'), new Length(max: 500)]),
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

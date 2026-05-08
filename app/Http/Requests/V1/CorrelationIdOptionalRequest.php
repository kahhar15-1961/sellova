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

/**
 * Shared strict-but-minimal body for write endpoints with optional notes.
 */
final class CorrelationIdOptionalRequest extends AbstractValidatedRequest
{
    /**
     * @return array{correlation_id?: string, note?: string}
     */
    public static function payload(Request $request): array
    {
        return self::validate($request);
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
                'note' => new Optional([new Type('string'), new Length(max: 2000)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * FormRequest-style validation without Laravel's FormRequest base.
 */
abstract class AbstractValidatedRequest
{
    private static ?ValidatorInterface $validator = null;

    private static function validator(): ValidatorInterface
    {
        return self::$validator ??= Validation::createValidator();
    }

    /**
     * @return array<string, mixed>
     */
    public static function validate(Request $request): array
    {
        $payload = array_replace_recursive(
            $request->query->all(),
            $request->request->all(),
            self::jsonPayload($request),
        );
        $constraint = static::constraint();
        $violations = self::validator()->validate($payload, $constraint);
        if ($violations->count() > 0) {
            throw ValidationFailedException::fromViolations($violations);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonPayload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    abstract protected static function constraint(): Constraint;
}

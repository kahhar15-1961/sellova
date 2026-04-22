<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Authentication / registration invariant failure (distinct from HTTP form validation).
 */
final class AuthValidationFailedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $violations
     */
    public function __construct(
        public readonly string $reasonCode,
        public readonly array $violations = [],
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf('Authentication failed (code: %s).', $reasonCode);
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

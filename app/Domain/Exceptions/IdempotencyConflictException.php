<?php

namespace App\Domain\Exceptions;

/**
 * Same idempotency key reused with a different request payload or conflicting outcome.
 */
final class IdempotencyConflictException extends DomainException
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $scope,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Idempotency conflict for key "%s" in scope "%s".',
            $idempotencyKey,
            $scope,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

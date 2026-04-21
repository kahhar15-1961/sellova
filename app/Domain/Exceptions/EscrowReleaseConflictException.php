<?php

namespace App\Domain\Exceptions;

/**
 * Escrow cannot be released due to conflicting state, amounts, or parallel operations.
 */
final class EscrowReleaseConflictException extends DomainException
{
    public function __construct(
        public readonly int $escrowAccountId,
        public readonly string $reasonCode,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Escrow release conflict for escrow %d (code: %s).',
            $escrowAccountId,
            $reasonCode,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

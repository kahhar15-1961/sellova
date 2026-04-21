<?php

namespace App\Domain\Exceptions;

/**
 * Escrow lifecycle state machine violation.
 */
final class InvalidEscrowStateTransitionException extends DomainException
{
    public function __construct(
        public readonly int $escrowAccountId,
        public readonly string $fromState,
        public readonly string $toState,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Invalid escrow state transition for escrow %d: %s → %s',
            $escrowAccountId,
            $fromState,
            $toState,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

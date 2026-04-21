<?php

namespace App\Domain\Exceptions;

/**
 * Thrown when an aggregate would leave a valid state machine path.
 */
final class InvalidDomainStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $aggregate,
        public readonly int|string $aggregateId,
        public readonly string $fromState,
        public readonly string $toState,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Invalid state transition for %s(%s): %s → %s',
            $aggregate,
            (string) $aggregateId,
            $fromState,
            $toState,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

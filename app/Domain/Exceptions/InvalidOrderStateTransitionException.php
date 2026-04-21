<?php

namespace App\Domain\Exceptions;

/**
 * Order lifecycle state machine violation.
 */
final class InvalidOrderStateTransitionException extends DomainException
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Invalid order state transition for order %d: %s → %s',
            $orderId,
            $fromStatus,
            $toStatus,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

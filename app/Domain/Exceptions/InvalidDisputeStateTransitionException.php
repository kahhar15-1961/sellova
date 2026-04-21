<?php

namespace App\Domain\Exceptions;

/**
 * Dispute workflow state machine violation.
 */
final class InvalidDisputeStateTransitionException extends DomainException
{
    public function __construct(
        public readonly int $disputeCaseId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Invalid dispute state transition for case %d: %s → %s',
            $disputeCaseId,
            $fromStatus,
            $toStatus,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

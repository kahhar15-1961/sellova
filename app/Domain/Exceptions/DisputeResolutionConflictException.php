<?php

namespace App\Domain\Exceptions;

/**
 * Dispute cannot be resolved as requested (evidence window, amounts, escrow linkage, etc.).
 */
final class DisputeResolutionConflictException extends DomainException
{
    public function __construct(
        public readonly int $disputeCaseId,
        public readonly string $reasonCode,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Dispute resolution conflict for case %d (code: %s).',
            $disputeCaseId,
            $reasonCode,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

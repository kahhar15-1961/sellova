<?php

namespace App\Domain\Exceptions;

/**
 * Withdrawal request failed business or compliance validation before payout.
 */
final class WithdrawalValidationFailedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $violations
     */
    public function __construct(
        public readonly int $withdrawalRequestId,
        public readonly string $reasonCode,
        public readonly array $violations = [],
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Withdrawal validation failed for request %d (code: %s).',
            $withdrawalRequestId,
            $reasonCode,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

<?php

namespace App\Domain\Exceptions;

/**
 * Invalid wallet ledger operation against business invariants.
 */
final class InvalidLedgerOperationException extends DomainException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf('Invalid wallet ledger operation (code: %s).', $reasonCode);
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

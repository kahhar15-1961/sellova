<?php

namespace App\Domain\Exceptions;

/**
 * Ledger operation currency does not match wallet currency.
 */
final class WalletCurrencyMismatchException extends DomainException
{
    public function __construct(
        public readonly int $walletId,
        public readonly string $walletCurrency,
        public readonly string $requestedCurrency,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Currency mismatch for wallet %d: wallet=%s, requested=%s.',
            $walletId,
            $walletCurrency,
            $requestedCurrency,
        );

        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

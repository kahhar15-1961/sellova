<?php

namespace App\Domain\Exceptions;

/**
 * Wallet has insufficient available balance for the requested operation.
 */
final class InsufficientWalletBalanceException extends DomainException
{
    public function __construct(
        public readonly int $walletId,
        public readonly string $currency,
        public readonly string $requestedAmount,
        public readonly ?string $availableAmount = null,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf(
            'Insufficient wallet balance for wallet %d (%s): requested %s',
            $walletId,
            $currency,
            $requestedAmount,
        );
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

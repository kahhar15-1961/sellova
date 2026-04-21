<?php

namespace App\Domain\Exceptions;

/**
 * Wallet aggregate not found.
 */
final class WalletNotFoundException extends DomainException
{
    public function __construct(
        public readonly int $walletId,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = sprintf('Wallet %d not found.', $walletId);
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

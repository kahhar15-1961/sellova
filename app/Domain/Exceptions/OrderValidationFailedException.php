<?php

namespace App\Domain\Exceptions;

/**
 * Order or checkout invariant violated (cart, totals, seller split, payment coupling).
 */
final class OrderValidationFailedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $violations
     */
    public function __construct(
        public readonly ?int $orderId,
        public readonly string $reasonCode,
        public readonly array $violations = [],
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $id = $orderId !== null ? (string) $orderId : 'new';
        $default = sprintf('Order validation failed for order %s (code: %s).', $id, $reasonCode);
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

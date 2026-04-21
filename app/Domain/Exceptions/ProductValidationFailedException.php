<?php

namespace App\Domain\Exceptions;

/**
 * Product or catalog invariant violated (pricing, type, inventory, ownership).
 */
final class ProductValidationFailedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $violations
     */
    public function __construct(
        public readonly ?int $productId,
        public readonly string $reasonCode,
        public readonly array $violations = [],
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $id = $productId !== null ? (string) $productId : 'new';
        $default = sprintf('Product validation failed for product %s (code: %s).', $id, $reasonCode);
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

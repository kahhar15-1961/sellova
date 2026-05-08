<?php

namespace App\Domain\Exceptions;

/**
 * Promo code or promotion eligibility failed.
 */
final class PromotionValidationFailedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $violations
     */
    public function __construct(
        public readonly ?string $promoCode,
        public readonly string $reasonCode,
        public readonly array $violations = [],
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $codeLabel = $promoCode !== null && $promoCode !== '' ? $promoCode : 'unknown';
        $default = sprintf('Promotion validation failed for promo code %s (code: %s).', $codeLabel, $reasonCode);
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}

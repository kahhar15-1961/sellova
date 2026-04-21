<?php

namespace App\Domain\Value;

/**
 * Data required to create a seller profile for an existing user.
 */
final readonly class SellerProfileDraft
{
    public function __construct(
        public string $displayName,
        public ?string $legalName,
        public string $countryCode,
        public string $defaultCurrency,
    ) {
    }
}

<?php

namespace App\Domain\Commands\Auth;

/**
 * Input contract for {@see \App\Services\Auth\AuthService::registerSeller}.
 */
final readonly class RegisterSellerCommand
{
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public string $passwordPlain,
        public string $displayName,
        public string $legalName,
        public string $countryCode,
        public string $defaultCurrency,
    ) {
    }
}

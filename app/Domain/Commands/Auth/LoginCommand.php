<?php

namespace App\Domain\Commands\Auth;

/**
 * Input contract for {@see \App\Services\Auth\AuthService::login}.
 */
final readonly class LoginCommand
{
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public string $passwordPlain,
        public ?string $deviceName,
    ) {
    }
}
